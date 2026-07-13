<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use DigitalElvis\NeuronAIStudio\Codegen\WorkflowClassImporter;
use DigitalElvis\NeuronAIStudio\Jobs\ResumeWorkflowJob;
use DigitalElvis\NeuronAIStudio\Jobs\RunWorkflowJob;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioTrace;
use DigitalElvis\NeuronAIStudio\Models\StudioTraceSpan;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\HumanInputRequiredException;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\ParallelBranchInterruptException;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\ToolApprovalRequiredException;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\WorkflowExecutionException;
use DigitalElvis\NeuronAIStudio\Support\ChatThreadKey;
use Illuminate\Support\Str;
use NeuronAI\Workflow\Interrupt\WorkflowInterrupt;
use NeuronAI\Workflow\Persistence\InMemoryPersistence;
use NeuronAI\Workflow\Workflow;
use NeuronAI\Workflow\WorkflowState;
use RuntimeException;
use Throwable;

class WorkflowRunner
{
    public function __construct(
        protected GraphValidator $validator,
        protected GraphExecutionLoop $executionLoop,
        protected WorkflowClassImporter $classImporter,
    ) {}

    protected function createExecutionSession(WorkflowDefinition $workflow, array $input = []): array
    {
        $threadId = null;
        if (isset($input['thread_id']) && is_string($input['thread_id']) && $input['thread_id'] !== '') {
            $threadId = $input['thread_id'];
            if (str_contains($threadId, ':')) {
                $threadId = ChatThreadKey::publicId($threadId);
            }
        } else {
            $threadId = (string) Str::uuid();
        }

        $thread = StudioThread::firstOrCreate([
            'id' => $threadId,
        ], [
            'entity_type' => WorkflowDefinition::class,
            'entity_id' => $workflow->id,
        ]);

        $run = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'status' => 'running',
            'input' => $input,
            'started_at' => now(),
        ]);

        $trace = StudioTrace::create([
            'run_id' => $run->id,
        ]);

        return [$run, $trace];
    }

    /** @param  array<string, mixed>  $input */
    public function run(WorkflowDefinition $workflow, array $input = [], ?callable $emitter = null): StudioRun
    {
        if ($this->shouldRunNative($workflow)) {
            return $this->runNative($workflow, $input, $emitter);
        }

        return $this->runInterpreted($workflow, $input, $emitter);
    }

    /** @param  array<string, mixed>  $input */
    public function runExistingRun(StudioRun $run, WorkflowDefinition $workflow, array $input = [], ?callable $emitter = null): StudioRun
    {
        if (! in_array($run->status, ['queued', 'running'], true)) {
            throw new \InvalidArgumentException(sprintf(
                'Workflow run must be queued or running to execute, got "%s".',
                $run->status,
            ));
        }

        $updates = [];

        if ($run->started_at === null) {
            $updates['started_at'] = now();
        }

        if ($run->status === 'queued') {
            $updates['status'] = 'running';
        }

        if ($updates !== []) {
            $run->update($updates);
            $run = $run->fresh();
        }

        if ($this->shouldRunNative($workflow)) {
            return $this->runNative($workflow, $input, $emitter, $run);
        }

        return $this->runInterpreted($workflow, $input, $emitter, $run);
    }

    /** @param  array<string, mixed>  $input */
    public function dispatch(WorkflowDefinition $workflow, array $input = []): StudioRun
    {
        if (! config('neuronai-studio.async_runs_enabled')) {
            throw new RuntimeException(
                'Async workflow runs are disabled. Enable async_runs_enabled in neuronai-studio config or use the synchronous stream endpoint.',
            );
        }

        [$run, $trace] = $this->createExecutionSession($workflow, $input);

        $run->update([
            'status' => 'queued',
            'started_at' => null,
        ]);

        RunWorkflowJob::dispatch($run->id, $workflow->id, $input);

        return $run->fresh();
    }

    /** @param  array<int, array<string, mixed>>  $attachments */
    public function dispatchResume(StudioRun $run, string $nodeId, string $message, array $attachments = [], ?string $approval = null): StudioRun
    {
        if (! config('neuronai-studio.async_runs_enabled')) {
            throw new RuntimeException(
                'Async workflow runs are disabled. Enable async_runs_enabled in neuronai-studio config or use the synchronous stream endpoint.',
            );
        }

        $requiredStatus = $approval !== null ? 'awaiting_tool_approval' : 'awaiting_input';

        if ($run->status !== $requiredStatus) {
            throw new \InvalidArgumentException(sprintf(
                'Workflow run must be %s to resume asynchronously, got "%s".',
                $requiredStatus,
                $run->status,
            ));
        }

        $run->update([
            'status' => 'queued',
            'finished_at' => null,
        ]);

        ResumeWorkflowJob::dispatch($run->id, $nodeId, $message, $attachments, $approval);

        return $run->fresh();
    }

    /** @param  array<string, mixed>  $input */
    protected function runInterpreted(WorkflowDefinition $workflow, array $input = [], ?callable $emitter = null, ?StudioRun $existingRun = null): StudioRun
    {
        $this->validator->assertValid($workflow->graph);

        if ($existingRun !== null) {
            $run = $existingRun;
            $trace = $run->traces()->latest()->first() ?? StudioTrace::create(['run_id' => $run->id]);
        } else {
            [$run, $trace] = $this->createExecutionSession($workflow, $input);
        }

        $state = null;

        try {
            $graphContext = new GraphContext(
                $workflow->graph['nodes'] ?? [],
                $workflow->graph['edges'] ?? [],
            );

            $state = $this->buildInitialState($graphContext, $run, $trace, $workflow, $input, $emitter);

            $interpreter = new GraphInterpreterWorkflow($graphContext, $state);
            $tracker = new TelemetryTracker($run, $trace, false);
            $interpreter->observe($tracker);

            $interpreter->bootstrap();

            $finalState = $interpreter->init()->run();

            return $this->finalizeRun($run, $trace, $finalState);
        } catch (HumanInputRequiredException $exception) {
            return $this->pauseForHumanInput($run, $exception, $state, $emitter);
        } catch (ParallelBranchInterruptException $exception) {
            return $this->pauseForParallelInterrupt($run, $exception, $state, $emitter);
        } catch (ToolApprovalRequiredException $exception) {
            return $this->pauseForToolApproval($run, $exception, $state, $emitter);
        } catch (Throwable $exception) {
            throw new WorkflowExecutionException(
                $this->finalizeFailedRun($run, $trace, $state, $exception),
                $exception,
            );
        }
    }

    /** @param  array<string, mixed>  $input */
    protected function runNative(WorkflowDefinition $workflow, array $input = [], ?callable $emitter = null, ?StudioRun $existingRun = null): StudioRun
    {
        $class = (string) $workflow->class_path;

        if ($existingRun !== null) {
            $run = $existingRun;
            $trace = $run->traces()->latest()->first() ?? StudioTrace::create(['run_id' => $run->id]);
        } else {
            [$run, $trace] = $this->createExecutionSession($workflow, $input);
        }

        try {
            $stateData = $this->buildNativeStateData($run, $input);
            $state = new WorkflowState($stateData);
            $middleware = new StudioTraceMiddleware;

            if ($emitter !== null) {
                $middleware->stepEmitter = fn (string $event, array $data) => $emitter($event, array_merge($data, [
                    'trace_id' => $run->id,
                ]));
            }

            $resumeToken = $run->id;

            /** @var Workflow $instance */
            $instance = app($class);
            $instance->setPersistence(new InMemoryPersistence, $resumeToken);
            $instance->addGlobalMiddleware($middleware);
            $instance->setState(new WorkflowState($stateData));

            $tracker = new TelemetryTracker($run, $trace, false);
            $instance->observe($tracker);

            $instance->bootstrap();

            $finalState = $instance->init()->run();

            $this->persistTraceSpans($trace, $middleware->steps);

            $run->update([
                'status' => 'completed',
                'output' => $this->outputWithNativeSteps($finalState->all(), $middleware->steps, $workflow),
                'checkpoint_state' => null,
                'finished_at' => now(),
                'prompt_tokens' => StudioTraceSpan::where('trace_id', $trace->id)->sum('prompt_tokens'),
                'completion_tokens' => StudioTraceSpan::where('trace_id', $trace->id)->sum('completion_tokens'),
                'total_tokens' => StudioTraceSpan::where('trace_id', $trace->id)->sum('total_tokens'),
            ]);

            return $run->fresh();
        } catch (WorkflowInterrupt $interrupt) {
            return $this->pauseForNativeInterrupt($run, $interrupt, $emitter);
        } catch (Throwable $exception) {
            throw new WorkflowExecutionException(
                $this->finalizeFailedRun($run, $trace, null, $exception),
                $exception,
            );
        }
    }

    public function resume(StudioRun $run, string $nodeId, string $message, ?callable $emitter = null, array $attachments = [], ?string $approval = null): StudioRun
    {
        $workflow = $run->thread->entity ?? WorkflowDefinition::findOrFail($run->input['workflow_definition_id'] ?? null);

        if ($approval === null && $this->shouldRunNative($workflow) && is_array($run->checkpoint_state['interrupt'] ?? null)) {
            return $this->resumeNative($run, $message, $emitter, $attachments);
        }

        return $this->resumeInterpreted($run, $nodeId, $message, $emitter, $attachments, $approval);
    }

    public function resumeInterpreted(StudioRun $run, string $nodeId, string $message, ?callable $emitter = null, array $attachments = [], ?string $approval = null): StudioRun
    {
        if ($approval !== null) {
            return $this->resumeToolApproval($run, $nodeId, $approval, $message, $emitter);
        }

        if (($run->checkpoint_state['kind'] ?? null) === 'parallel') {
            return $this->resumeParallel($run, $message, $emitter, $attachments);
        }

        $workflow = $run->thread->entity ?? WorkflowDefinition::findOrFail($run->input['workflow_definition_id'] ?? null);
        $checkpoint = $run->checkpoint_state ?? [];
        $nodeConfig = (new GraphContext(
            $workflow->graph['nodes'] ?? [],
            $workflow->graph['edges'] ?? [],
        ))->nodeConfig($nodeId);

        $outputKey = (string) (($nodeConfig['data']['output_key'] ?? null) ?: 'human_response');

        $graphContext = new GraphContext(
            $workflow->graph['nodes'] ?? [],
            $workflow->graph['edges'] ?? [],
        );

        $stateData = is_array($checkpoint['state'] ?? null) ? $checkpoint['state'] : [];
        unset($stateData['__steps']);
        $stateData[$outputKey] = $message;

        if ($attachments !== []) {
            $stateData['attachments'] = $attachments;
        }

        $state = new BuilderWorkflowState($graphContext, null, $stateData);

        if ($emitter !== null) {
            $state->stepEmitter = fn (string $event, array $data) => $emitter($event, array_merge($data, [
                'trace_id' => $run->id,
            ]));
        }

        $run->update([
            'status' => 'running',
        ]);

        $trace = $run->traces()->latest()->first() ?? StudioTrace::create(['run_id' => $run->id]);

        try {
            $nextNodeId = $graphContext->targetForHandle($nodeId) ?? '';
            $this->recordHumanStep($run, $trace, $nodeId, $state, $outputKey, $message);

            if ($nextNodeId === '') {
                $run->update([
                    'status' => 'completed',
                    'output' => $state->all(),
                    'checkpoint_state' => null,
                    'finished_at' => now(),
                ]);

                return $run->fresh();
            }

            $state->set('__current_node_id', $nextNodeId);
            $finalState = $this->executionLoop->runFromNode($nextNodeId, $graphContext, $state);

            return $this->finalizeRun($run, $trace, $finalState);
        } catch (HumanInputRequiredException $exception) {
            return $this->pauseForHumanInput($run, $exception, $state, $emitter);
        } catch (ParallelBranchInterruptException $exception) {
            return $this->pauseForParallelInterrupt($run, $exception, $state, $emitter);
        } catch (ToolApprovalRequiredException $exception) {
            return $this->pauseForToolApproval($run, $exception, $state, $emitter);
        } catch (Throwable $exception) {
            throw new WorkflowExecutionException(
                $this->finalizeFailedRun($run, $trace, $state, $exception),
                $exception,
            );
        }
    }

    protected function resumeParallel(
        StudioRun $run,
        string $message,
        ?callable $emitter = null,
        array $attachments = [],
    ): StudioRun {
        $workflow = $run->thread->entity ?? WorkflowDefinition::findOrFail($run->input['workflow_definition_id'] ?? null);
        $checkpoint = $run->checkpoint_state ?? [];
        $parallel = is_array($checkpoint['parallel'] ?? null) ? $checkpoint['parallel'] : [];

        $graphContext = new GraphContext(
            $workflow->graph['nodes'] ?? [],
            $workflow->graph['edges'] ?? [],
        );

        $forkId = (string) ($parallel['fork_id'] ?? '');

        $stateData = is_array($checkpoint['state'] ?? null) ? $checkpoint['state'] : [];
        unset($stateData['__steps']);

        $stateData['__parallel_resume'] = [
            'fork_id' => $forkId,
            'join_id' => (string) ($parallel['join_id'] ?? ''),
            'completed' => is_array($parallel['completed'] ?? null) ? $parallel['completed'] : [],
            'completed_outputs' => is_array($parallel['completed_outputs'] ?? null) ? $parallel['completed_outputs'] : [],
            'pending' => [
                'branch_id' => (string) ($parallel['pending_branch'] ?? ''),
                'node_id' => (string) ($parallel['pending_node'] ?? ''),
                'output_key' => (string) ($parallel['output_key'] ?? 'human_response'),
                'response' => $message,
                'state' => is_array($parallel['pending_state'] ?? null) ? $parallel['pending_state'] : [],
            ],
        ];

        if ($attachments !== []) {
            $stateData['attachments'] = $attachments;
        }

        $state = new BuilderWorkflowState($graphContext, null, $stateData);

        if ($emitter !== null) {
            $state->stepEmitter = fn (string $event, array $data) => $emitter($event, array_merge($data, [
                'trace_id' => $run->id,
            ]));
        }

        $run->update([
            'status' => 'running',
        ]);

        $trace = $run->traces()->latest()->first() ?? StudioTrace::create(['run_id' => $run->id]);

        try {
            $state->set('__current_node_id', $forkId);
            $finalState = $this->executionLoop->runFromNode($forkId, $graphContext, $state);

            return $this->finalizeRun($run, $trace, $finalState);
        } catch (HumanInputRequiredException $exception) {
            return $this->pauseForHumanInput($run, $exception, $state, $emitter);
        } catch (ParallelBranchInterruptException $exception) {
            return $this->pauseForParallelInterrupt($run, $exception, $state, $emitter);
        } catch (ToolApprovalRequiredException $exception) {
            return $this->pauseForToolApproval($run, $exception, $state, $emitter);
        } catch (Throwable $exception) {
            throw new WorkflowExecutionException(
                $this->finalizeFailedRun($run, $trace, $state, $exception),
                $exception,
            );
        }
    }

    protected function resumeToolApproval(
        StudioRun $run,
        string $nodeId,
        string $approval,
        string $feedback,
        ?callable $emitter,
    ): StudioRun {
        $workflow = $run->thread->entity ?? WorkflowDefinition::findOrFail($run->input['workflow_definition_id'] ?? null);
        $checkpoint = $run->checkpoint_state ?? [];

        $graphContext = new GraphContext(
            $workflow->graph['nodes'] ?? [],
            $workflow->graph['edges'] ?? [],
        );

        $nodeId = $nodeId !== '' ? $nodeId : (string) ($checkpoint['node_id'] ?? $run->awaiting_node_id ?? '');

        $stateData = is_array($checkpoint['state'] ?? null) ? $checkpoint['state'] : [];
        unset($stateData['__steps']);
        $stateData['__tool_approval_resume'] = [
            'node_id' => $nodeId,
            'decision' => $approval,
            'feedback' => $feedback,
            'interrupt' => (string) ($checkpoint['interrupt'] ?? ''),
        ];

        $state = new BuilderWorkflowState($graphContext, null, $stateData);

        if ($emitter !== null) {
            $state->stepEmitter = fn (string $event, array $data) => $emitter($event, array_merge($data, [
                'trace_id' => $run->id,
            ]));
        }

        $run->update([
            'status' => 'running',
        ]);

        $trace = $run->traces()->latest()->first() ?? StudioTrace::create(['run_id' => $run->id]);

        if ($emitter !== null) {
            $emitter('tool_approval_resolved', [
                'trace_id' => $run->id,
                'node_id' => $nodeId,
                'approved' => $approval !== 'reject',
            ]);
        }

        try {
            $state->set('__current_node_id', $nodeId);
            $finalState = $this->executionLoop->runFromNode($nodeId, $graphContext, $state);

            return $this->finalizeRun($run, $trace, $finalState);
        } catch (HumanInputRequiredException $exception) {
            return $this->pauseForHumanInput($run, $exception, $state, $emitter);
        } catch (ToolApprovalRequiredException $exception) {
            return $this->pauseForToolApproval($run, $exception, $state, $emitter);
        } catch (Throwable $exception) {
            throw new WorkflowExecutionException(
                $this->finalizeFailedRun($run, $trace, $state, $exception),
                $exception,
            );
        }
    }

    protected function resumeNative(StudioRun $run, string $message, ?callable $emitter = null, array $attachments = []): StudioRun
    {
        $workflow = $run->thread->entity ?? WorkflowDefinition::findOrFail($run->input['workflow_definition_id'] ?? null);
        $class = (string) $workflow->class_path;
        $checkpoint = $run->checkpoint_state ?? [];
        $outputKey = (string) ($checkpoint['output_key'] ?? 'human_response');
        $stateData = is_array($checkpoint['state'] ?? null) ? $checkpoint['state'] : [];
        $stateData[$outputKey] = $message;

        if ($attachments !== []) {
            $stateData['attachments'] = $attachments;
        }

        $run->update([
            'status' => 'running',
        ]);

        $trace = $run->traces()->latest()->first() ?? StudioTrace::create(['run_id' => $run->id]);

        try {
            $state = new WorkflowState($stateData);
            $middleware = new StudioTraceMiddleware;

            if ($emitter !== null) {
                $middleware->stepEmitter = fn (string $event, array $data) => $emitter($event, array_merge($data, [
                    'trace_id' => $run->id,
                ]));
            }

            /** @var Workflow $instance */
            $instance = app($class);
            $instance->addGlobalMiddleware($middleware);
            $instance->setState(new WorkflowState($stateData));

            $tracker = new TelemetryTracker($run, $trace, false);
            $instance->observe($tracker);

            $instance->bootstrap();

            $resumeRequest = isset($checkpoint['interrupt']['request']) ? unserialize(base64_decode($checkpoint['interrupt']['request'])) : null;
            $finalState = $resumeRequest !== null
                ? $instance->init($resumeRequest)->run()
                : $instance->init()->run();

            $this->persistTraceSpans($trace, $middleware->steps);

            $run->update([
                'status' => 'completed',
                'output' => $this->outputWithNativeSteps($finalState->all(), $middleware->steps, $workflow),
                'checkpoint_state' => null,
                'finished_at' => now(),
                'prompt_tokens' => StudioTraceSpan::where('trace_id', $trace->id)->sum('prompt_tokens'),
                'completion_tokens' => StudioTraceSpan::where('trace_id', $trace->id)->sum('completion_tokens'),
                'total_tokens' => StudioTraceSpan::where('trace_id', $trace->id)->sum('total_tokens'),
            ]);

            return $run->fresh();
        } catch (WorkflowInterrupt $interrupt) {
            return $this->pauseForNativeInterrupt($run, $interrupt, $emitter);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            throw $exception;
        }
    }

    protected function shouldRunNative(WorkflowDefinition $workflow): bool
    {
        $classPath = $workflow->class_path;

        return is_string($classPath)
            && $classPath !== ''
            && str_starts_with($classPath, 'class:') === false
            && str_starts_with($classPath, 'json:') === false
            && $this->classImporter->isNativeWorkflow($classPath);
    }

    /** @param  array<string, mixed>  $input */
    protected function buildNativeStateData(StudioRun $run, array $input): array
    {
        $message = (string) ($input['message'] ?? $input['input'] ?? '');
        $initialState = is_array($input['state'] ?? null) ? $input['state'] : [];

        $stateData = array_merge($initialState, [
            'input' => $message,
            '__studio_run_id' => $run->id,
            '__studio_thread_id' => $run->thread_id,
        ]);

        if (! empty($input['attachments']) && is_array($input['attachments'])) {
            $stateData['attachments'] = $input['attachments'];
        }

        return $stateData;
    }

    /** @param  array<string, mixed>  $input */
    protected function buildInitialState(GraphContext $graphContext, StudioRun $run, StudioTrace $trace, WorkflowDefinition $workflow, array $input, ?callable $emitter): BuilderWorkflowState
    {
        $message = (string) ($input['message'] ?? $input['input'] ?? '');
        $initialState = is_array($input['state'] ?? null) ? $input['state'] : [];

        $stateData = array_merge($initialState, [
            'input' => $message,
            '__workflow_trace_id' => $trace->id,
            '__studio_run_id' => $run->id,
            '__studio_trace_id' => $trace->id,
            '__studio_thread_id' => $run->thread_id,
        ]);

        if (! empty($input['attachments']) && is_array($input['attachments'])) {
            $stateData['attachments'] = $input['attachments'];
        }

        $state = new BuilderWorkflowState($graphContext, null, $stateData);

        if ($emitter !== null) {
            $state->stepEmitter = fn (string $event, array $data) => $emitter($event, array_merge($data, [
                'trace_id' => $run->id,
            ]));
        }

        return $state;
    }

    protected function finalizeRun(StudioRun $run, StudioTrace $trace, BuilderWorkflowState $finalState): StudioRun
    {
        $this->persistTraceSpans($trace, $finalState->get('__steps', []));

        $run->update([
            'status' => 'completed',
            'output' => $finalState->all(),
            'checkpoint_state' => null,
            'finished_at' => now(),
            'prompt_tokens' => StudioTraceSpan::where('trace_id', $trace->id)->sum('prompt_tokens'),
            'completion_tokens' => StudioTraceSpan::where('trace_id', $trace->id)->sum('completion_tokens'),
            'total_tokens' => StudioTraceSpan::where('trace_id', $trace->id)->sum('total_tokens'),
        ]);

        return $run->fresh();
    }

    protected function finalizeFailedRun(
        StudioRun $run,
        StudioTrace $trace,
        ?BuilderWorkflowState $state,
        Throwable $exception,
    ): StudioRun {
        if ($state !== null) {
            $this->persistTraceSpans($trace, $state->get('__steps', []));

            $run->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'output' => $state->all(),
                'checkpoint_state' => null,
                'finished_at' => now(),
                'prompt_tokens' => StudioTraceSpan::where('trace_id', $trace->id)->sum('prompt_tokens'),
                'completion_tokens' => StudioTraceSpan::where('trace_id', $trace->id)->sum('completion_tokens'),
                'total_tokens' => StudioTraceSpan::where('trace_id', $trace->id)->sum('total_tokens'),
            ]);
        } else {
            $run->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
                'prompt_tokens' => StudioTraceSpan::where('trace_id', $trace->id)->sum('prompt_tokens'),
                'completion_tokens' => StudioTraceSpan::where('trace_id', $trace->id)->sum('completion_tokens'),
                'total_tokens' => StudioTraceSpan::where('trace_id', $trace->id)->sum('total_tokens'),
            ]);
        }

        return $run->fresh();
    }

    /** @param  list<array<string, mixed>>  $steps */
    protected function persistTraceSpans(StudioTrace $trace, array $steps): void
    {
        if ($steps === []) {
            return;
        }

        if ($trace->spans()->where('type', 'node')->exists()) {
            return;
        }

        foreach ($steps as $step) {
            StudioTraceSpan::create([
                'trace_id' => $trace->id,
                'name' => $step['node_id'],
                'type' => 'node',
                'status' => 'completed',
                'input' => null,
                'output' => ['state_snapshot' => $step['state_snapshot'] ?? null],
                'duration_ms' => $step['duration_ms'] ?? null,
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        }
    }

    protected function pauseForHumanInput(
        StudioRun $run,
        HumanInputRequiredException $exception,
        ?BuilderWorkflowState $state,
        ?callable $emitter,
    ): StudioRun {
        $run->update([
            'status' => 'awaiting_input',
            'awaiting_node_id' => $exception->nodeId,
            'checkpoint_state' => [
                'state' => $state?->all() ?? [],
                'node_id' => $exception->nodeId,
                'output_key' => $exception->outputKey,
            ],
            'finished_at' => null,
        ]);

        if ($emitter !== null) {
            $emitter('human_input_required', [
                'trace_id' => $run->id,
                'node_id' => $exception->nodeId,
                'prompt' => $exception->prompt,
                'output_key' => $exception->outputKey,
            ]);
        }

        return $run->fresh();
    }

    protected function pauseForParallelInterrupt(
        StudioRun $run,
        ParallelBranchInterruptException $exception,
        ?BuilderWorkflowState $state,
        ?callable $emitter,
    ): StudioRun {
        $run->update([
            'status' => 'awaiting_input',
            'awaiting_node_id' => $exception->pendingNodeId,
            'checkpoint_state' => [
                'state' => $state?->all() ?? [],
                'node_id' => $exception->forkId,
                'kind' => 'parallel',
                'output_key' => $exception->outputKey,
                'parallel' => [
                    'fork_id' => $exception->forkId,
                    'join_id' => $exception->joinId,
                    'pending_branch' => $exception->branchId,
                    'pending_node' => $exception->pendingNodeId,
                    'output_key' => $exception->outputKey,
                    'pending_state' => $exception->pendingState,
                    'completed' => $exception->completedResults,
                    'completed_outputs' => $exception->completedOutputs,
                ],
            ],
            'finished_at' => null,
        ]);

        if ($emitter !== null) {
            $emitter('parallel_interrupt', [
                'trace_id' => $run->id,
                'fork_id' => $exception->forkId,
                'branch_id' => $exception->branchId,
                'node_id' => $exception->pendingNodeId,
                'reason' => $exception->reason,
            ]);

            $emitter('human_input_required', [
                'trace_id' => $run->id,
                'node_id' => $exception->pendingNodeId,
                'prompt' => $exception->prompt,
                'output_key' => $exception->outputKey,
            ]);
        }

        return $run->fresh();
    }

    protected function pauseForToolApproval(
        StudioRun $run,
        ToolApprovalRequiredException $exception,
        ?BuilderWorkflowState $state,
        ?callable $emitter,
    ): StudioRun {
        $run->update([
            'status' => 'awaiting_tool_approval',
            'awaiting_node_id' => $exception->nodeId,
            'checkpoint_state' => [
                'state' => $state?->all() ?? [],
                'node_id' => $exception->nodeId,
                'pending_tools' => $exception->pendingTools,
                'interrupt' => $exception->serializedInterrupt,
            ],
            'finished_at' => null,
        ]);

        if ($emitter !== null) {
            $emitter('tool_approval_required', [
                'trace_id' => $run->id,
                'node_id' => $exception->nodeId,
                'pending_tools' => $exception->pendingTools,
                'message' => $exception->approvalMessage,
            ]);
        }

        return $run->fresh();
    }

    protected function pauseForNativeInterrupt(
        StudioRun $run,
        WorkflowInterrupt $interrupt,
        ?callable $emitter,
    ): StudioRun {
        $request = $interrupt->getRequest();
        $node = $interrupt->getNode();
        $nodeId = defined($node::class.'::STUDIO_NODE_ID')
            ? (string) constant($node::class.'::STUDIO_NODE_ID')
            : 'human';

        $run->update([
            'status' => 'awaiting_input',
            'checkpoint_state' => [
                'state' => $interrupt->getState()->all(),
                'node_id' => $nodeId,
                'output_key' => 'human_response',
                'interrupt' => [
                    'workflow_id' => $interrupt->getWorkflowId(),
                    'request' => base64_encode(serialize($request)),
                    'resume_token' => $run->id,
                ],
            ],
            'finished_at' => null,
        ]);

        if ($emitter !== null) {
            $emitter('human_input_required', [
                'trace_id' => $run->id,
                'node_id' => $nodeId,
                'prompt' => $request->getMessage(),
                'output_key' => 'human_response',
            ]);
        }

        return $run->fresh();
    }

    protected function recordHumanStep(
        StudioRun $run,
        StudioTrace $trace,
        string $nodeId,
        BuilderWorkflowState $state,
        string $outputKey,
        string $message,
    ): void {
        $state->emitStep('step_started', [
            'node_id' => $nodeId,
            'node_type' => 'human',
        ]);

        StudioTraceSpan::create([
            'trace_id' => $trace->id,
            'name' => $nodeId,
            'type' => 'node',
            'status' => 'completed',
            'input' => null,
            'output' => ['state_snapshot' => array_merge($state->all(), [$outputKey => $message])],
            'duration_ms' => 0,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $state->emitStep('step_completed', [
            'node_id' => $nodeId,
            'node_type' => 'human',
            'handle' => 'default',
            'duration_ms' => 0,
        ]);
    }

    /** @param  array<int, array<string, mixed>>  $steps */
    protected function outputWithNativeSteps(array $output, array $steps, WorkflowDefinition $workflow): array
    {
        $output['__steps'] = $this->normalizeNativeSteps($steps, $workflow);

        return $output;
    }

    /** @param  array<int, array<string, mixed>>  $steps
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeNativeSteps(array $steps, WorkflowDefinition $workflow): array
    {
        $graphContext = new GraphContext(
            $workflow->graph['nodes'] ?? [],
            $workflow->graph['edges'] ?? [],
        );

        return array_map(function (array $step) use ($graphContext) {
            $nodeId = (string) ($step['node_id'] ?? '');
            $nodeConfig = $graphContext->nodeConfig($nodeId);
            $canvasType = (string) ($nodeConfig['type'] ?? '');

            if ($canvasType !== '') {
                $step['node_type'] = $canvasType;
            }

            return $step;
        }, $steps);
    }
}
