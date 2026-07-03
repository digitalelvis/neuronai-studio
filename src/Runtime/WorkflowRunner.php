<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use DigitalElvis\NeuronAIStudio\Codegen\WorkflowClassImporter;
use DigitalElvis\NeuronAIStudio\Jobs\ResumeWorkflowJob;
use DigitalElvis\NeuronAIStudio\Jobs\RunWorkflowJob;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Models\WorkflowTrace;
use DigitalElvis\NeuronAIStudio\Models\WorkflowTraceStep;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\HumanInputRequiredException;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\ParallelBranchInterruptException;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\ToolApprovalRequiredException;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\WorkflowExecutionException;
use DigitalElvis\NeuronAIStudio\Support\ChatThreadKey;
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

    /** @param  array<string, mixed>  $input */
    public function run(WorkflowDefinition $workflow, array $input = [], ?callable $emitter = null): WorkflowTrace
    {
        if ($this->shouldRunNative($workflow)) {
            return $this->runNative($workflow, $input, $emitter);
        }

        return $this->runInterpreted($workflow, $input, $emitter);
    }

    /** @param  array<string, mixed>  $input */
    public function runExistingTrace(WorkflowTrace $trace, WorkflowDefinition $workflow, array $input = [], ?callable $emitter = null): WorkflowTrace
    {
        if (! in_array($trace->status, ['queued', 'running'], true)) {
            throw new \InvalidArgumentException(sprintf(
                'Workflow trace must be queued or running to execute, got "%s".',
                $trace->status,
            ));
        }

        $updates = [];

        if ($trace->started_at === null) {
            $updates['started_at'] = now();
        }

        if ($trace->status === 'queued') {
            $updates['status'] = 'running';
        }

        if ($updates !== []) {
            $trace->update($updates);
            $trace = $trace->fresh();
        }

        if ($this->shouldRunNative($workflow)) {
            return $this->runNative($workflow, $input, $emitter, $trace);
        }

        return $this->runInterpreted($workflow, $input, $emitter, $trace);
    }

    /** @param  array<string, mixed>  $input */
    public function dispatch(WorkflowDefinition $workflow, array $input = []): WorkflowTrace
    {
        if (! config('neuronai-studio.async_runs_enabled')) {
            throw new RuntimeException(
                'Async workflow runs are disabled. Enable async_runs_enabled in neuronai-studio config or use the synchronous stream endpoint.',
            );
        }

        $trace = WorkflowTrace::create([
            'workflow_definition_id' => $workflow->id,
            'status' => 'queued',
            'input' => $input,
            'started_at' => null,
        ]);

        RunWorkflowJob::dispatch($trace->id, $workflow->id, $input);

        return $trace->fresh();
    }

    /** @param  array<int, array<string, mixed>>  $attachments */
    public function dispatchResume(WorkflowTrace $trace, string $nodeId, string $message, array $attachments = [], ?string $approval = null): WorkflowTrace
    {
        if (! config('neuronai-studio.async_runs_enabled')) {
            throw new RuntimeException(
                'Async workflow runs are disabled. Enable async_runs_enabled in neuronai-studio config or use the synchronous stream endpoint.',
            );
        }

        $requiredStatus = $approval !== null ? 'awaiting_tool_approval' : 'awaiting_input';

        if ($trace->status !== $requiredStatus) {
            throw new \InvalidArgumentException(sprintf(
                'Workflow trace must be %s to resume asynchronously, got "%s".',
                $requiredStatus,
                $trace->status,
            ));
        }

        $trace->update([
            'status' => 'queued',
            'finished_at' => null,
        ]);

        ResumeWorkflowJob::dispatch($trace->id, $nodeId, $message, $attachments, $approval);

        return $trace->fresh();
    }

    /** @param  array<string, mixed>  $input */
    protected function runInterpreted(WorkflowDefinition $workflow, array $input = [], ?callable $emitter = null, ?WorkflowTrace $existingTrace = null): WorkflowTrace
    {
        $this->validator->assertValid($workflow->graph);

        $trace = $existingTrace ?? WorkflowTrace::create([
            'workflow_definition_id' => $workflow->id,
            'status' => 'running',
            'input' => $input,
            'started_at' => now(),
        ]);

        $state = null;

        try {
            $graphContext = new GraphContext(
                $workflow->graph['nodes'] ?? [],
                $workflow->graph['edges'] ?? [],
            );

            $state = $this->buildInitialState($graphContext, $trace->id, $workflow->id, $input, $emitter);

            $interpreter = new GraphInterpreterWorkflow($graphContext, $state);
            $interpreter->bootstrap();

            $finalState = $interpreter->init()->run();

            return $this->finalizeTrace($trace, $finalState);
        } catch (HumanInputRequiredException $exception) {
            return $this->pauseForHumanInput($trace, $exception, $state, $emitter);
        } catch (ParallelBranchInterruptException $exception) {
            return $this->pauseForParallelInterrupt($trace, $exception, $state, $emitter);
        } catch (ToolApprovalRequiredException $exception) {
            return $this->pauseForToolApproval($trace, $exception, $state, $emitter);
        } catch (Throwable $exception) {
            throw new WorkflowExecutionException(
                $this->finalizeFailedTrace($trace, $state, $exception),
                $exception,
            );
        }
    }

    /** @param  array<string, mixed>  $input */
    protected function runNative(WorkflowDefinition $workflow, array $input = [], ?callable $emitter = null, ?WorkflowTrace $existingTrace = null): WorkflowTrace
    {
        $class = (string) $workflow->class_path;

        $trace = $existingTrace ?? WorkflowTrace::create([
            'workflow_definition_id' => $workflow->id,
            'status' => 'running',
            'input' => $input,
            'started_at' => now(),
        ]);

        try {
            $stateData = $this->buildNativeStateData($workflow->id, $input);
            $state = new WorkflowState($stateData);
            $middleware = new StudioTraceMiddleware;

            if ($emitter !== null) {
                $middleware->stepEmitter = fn (string $event, array $data) => $emitter($event, array_merge($data, [
                    'trace_id' => $trace->id,
                ]));
            }

            $resumeToken = 'studio_trace_'.$trace->id;

            /** @var Workflow $instance */
            $instance = app($class);
            $instance->setPersistence(new InMemoryPersistence, $resumeToken);
            $instance->addGlobalMiddleware($middleware);
            $instance->setState(new WorkflowState($stateData));
            $instance->bootstrap();

            $finalState = $instance->init()->run();

            foreach ($middleware->steps as $step) {
                WorkflowTraceStep::create([
                    'workflow_trace_id' => $trace->id,
                    'node_id' => $step['node_id'],
                    'node_type' => $step['node_type'],
                    'state_snapshot' => $step['state_snapshot'] ?? null,
                    'duration_ms' => $step['duration_ms'] ?? null,
                ]);
            }

            $trace->update([
                'status' => 'completed',
                'output' => $this->outputWithNativeSteps($finalState->all(), $middleware->steps, $workflow),
                'checkpoint' => null,
                'awaiting_node_id' => null,
                'finished_at' => now(),
            ]);

            return $trace->fresh(['steps']);
        } catch (WorkflowInterrupt $interrupt) {
            return $this->pauseForNativeInterrupt($trace, $interrupt, $emitter);
        } catch (Throwable $exception) {
            throw new WorkflowExecutionException(
                $this->finalizeFailedTrace($trace, null, $exception),
                $exception,
            );
        }
    }

    public function resume(WorkflowTrace $trace, string $nodeId, string $message, ?callable $emitter = null, array $attachments = [], ?string $approval = null): WorkflowTrace
    {
        $workflow = $trace->workflow ?? WorkflowDefinition::findOrFail($trace->workflow_definition_id);

        if ($approval === null && $this->shouldRunNative($workflow) && is_array($trace->checkpoint['interrupt'] ?? null)) {
            return $this->resumeNative($trace, $message, $emitter, $attachments);
        }

        return $this->resumeInterpreted($trace, $nodeId, $message, $emitter, $attachments, $approval);
    }

    public function resumeInterpreted(WorkflowTrace $trace, string $nodeId, string $message, ?callable $emitter = null, array $attachments = [], ?string $approval = null): WorkflowTrace
    {
        if ($approval !== null) {
            return $this->resumeToolApproval($trace, $nodeId, $approval, $message, $emitter);
        }

        if (($trace->checkpoint['kind'] ?? null) === 'parallel') {
            return $this->resumeParallel($trace, $message, $emitter, $attachments);
        }

        $workflow = $trace->workflow ?? WorkflowDefinition::findOrFail($trace->workflow_definition_id);
        $checkpoint = $trace->checkpoint ?? [];
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

        $state = new BuilderWorkflowState($graphContext, $trace->id, $stateData);

        if ($emitter !== null) {
            $state->stepEmitter = fn (string $event, array $data) => $emitter($event, array_merge($data, [
                'trace_id' => $trace->id,
            ]));
        }

        $trace->update([
            'status' => 'running',
            'awaiting_node_id' => null,
        ]);

        try {
            $nextNodeId = $graphContext->targetForHandle($nodeId) ?? '';
            $this->recordHumanStep($trace, $nodeId, $state, $outputKey, $message);

            if ($nextNodeId === '') {
                $trace->update([
                    'status' => 'completed',
                    'output' => $state->all(),
                    'checkpoint' => null,
                    'finished_at' => now(),
                ]);

                return $trace->fresh(['steps']);
            }

            $state->set('__current_node_id', $nextNodeId);
            $finalState = $this->executionLoop->runFromNode($nextNodeId, $graphContext, $state);

            return $this->finalizeTrace($trace, $finalState);
        } catch (HumanInputRequiredException $exception) {
            return $this->pauseForHumanInput($trace, $exception, $state, $emitter);
        } catch (ParallelBranchInterruptException $exception) {
            return $this->pauseForParallelInterrupt($trace, $exception, $state, $emitter);
        } catch (ToolApprovalRequiredException $exception) {
            return $this->pauseForToolApproval($trace, $exception, $state, $emitter);
        } catch (Throwable $exception) {
            throw new WorkflowExecutionException(
                $this->finalizeFailedTrace($trace, $state, $exception),
                $exception,
            );
        }
    }

    protected function resumeParallel(
        WorkflowTrace $trace,
        string $message,
        ?callable $emitter = null,
        array $attachments = [],
    ): WorkflowTrace {
        $workflow = $trace->workflow ?? WorkflowDefinition::findOrFail($trace->workflow_definition_id);
        $checkpoint = $trace->checkpoint ?? [];
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

        $state = new BuilderWorkflowState($graphContext, $trace->id, $stateData);

        if ($emitter !== null) {
            $state->stepEmitter = fn (string $event, array $data) => $emitter($event, array_merge($data, [
                'trace_id' => $trace->id,
            ]));
        }

        $trace->update([
            'status' => 'running',
            'awaiting_node_id' => null,
        ]);

        try {
            $state->set('__current_node_id', $forkId);
            $finalState = $this->executionLoop->runFromNode($forkId, $graphContext, $state);

            return $this->finalizeTrace($trace, $finalState);
        } catch (HumanInputRequiredException $exception) {
            return $this->pauseForHumanInput($trace, $exception, $state, $emitter);
        } catch (ParallelBranchInterruptException $exception) {
            return $this->pauseForParallelInterrupt($trace, $exception, $state, $emitter);
        } catch (ToolApprovalRequiredException $exception) {
            return $this->pauseForToolApproval($trace, $exception, $state, $emitter);
        } catch (Throwable $exception) {
            throw new WorkflowExecutionException(
                $this->finalizeFailedTrace($trace, $state, $exception),
                $exception,
            );
        }
    }

    protected function resumeToolApproval(
        WorkflowTrace $trace,
        string $nodeId,
        string $approval,
        string $feedback,
        ?callable $emitter,
    ): WorkflowTrace {
        $workflow = $trace->workflow ?? WorkflowDefinition::findOrFail($trace->workflow_definition_id);
        $checkpoint = $trace->checkpoint ?? [];

        $graphContext = new GraphContext(
            $workflow->graph['nodes'] ?? [],
            $workflow->graph['edges'] ?? [],
        );

        $nodeId = $nodeId !== '' ? $nodeId : (string) ($checkpoint['node_id'] ?? $trace->awaiting_node_id ?? '');

        $stateData = is_array($checkpoint['state'] ?? null) ? $checkpoint['state'] : [];
        unset($stateData['__steps']);
        $stateData['__tool_approval_resume'] = [
            'node_id' => $nodeId,
            'decision' => $approval,
            'feedback' => $feedback,
            'interrupt' => (string) ($checkpoint['interrupt'] ?? ''),
        ];

        $state = new BuilderWorkflowState($graphContext, $trace->id, $stateData);

        if ($emitter !== null) {
            $state->stepEmitter = fn (string $event, array $data) => $emitter($event, array_merge($data, [
                'trace_id' => $trace->id,
            ]));
        }

        $trace->update([
            'status' => 'running',
            'awaiting_node_id' => null,
        ]);

        if ($emitter !== null) {
            $emitter('tool_approval_resolved', [
                'trace_id' => $trace->id,
                'node_id' => $nodeId,
                'approved' => $approval !== 'reject',
            ]);
        }

        try {
            $state->set('__current_node_id', $nodeId);
            $finalState = $this->executionLoop->runFromNode($nodeId, $graphContext, $state);

            return $this->finalizeTrace($trace, $finalState);
        } catch (HumanInputRequiredException $exception) {
            return $this->pauseForHumanInput($trace, $exception, $state, $emitter);
        } catch (ToolApprovalRequiredException $exception) {
            return $this->pauseForToolApproval($trace, $exception, $state, $emitter);
        } catch (Throwable $exception) {
            throw new WorkflowExecutionException(
                $this->finalizeFailedTrace($trace, $state, $exception),
                $exception,
            );
        }
    }

    protected function resumeNative(WorkflowTrace $trace, string $message, ?callable $emitter = null, array $attachments = []): WorkflowTrace
    {
        $workflow = $trace->workflow ?? WorkflowDefinition::findOrFail($trace->workflow_definition_id);
        $class = (string) $workflow->class_path;
        $checkpoint = $trace->checkpoint ?? [];
        $outputKey = (string) ($checkpoint['output_key'] ?? 'human_response');
        $stateData = is_array($checkpoint['state'] ?? null) ? $checkpoint['state'] : [];
        $stateData[$outputKey] = $message;

        if ($attachments !== []) {
            $stateData['attachments'] = $attachments;
        }

        $trace->update([
            'status' => 'running',
            'awaiting_node_id' => null,
        ]);

        try {
            $state = new WorkflowState($stateData);
            $middleware = new StudioTraceMiddleware;

            if ($emitter !== null) {
                $middleware->stepEmitter = fn (string $event, array $data) => $emitter($event, array_merge($data, [
                    'trace_id' => $trace->id,
                ]));
            }

            /** @var Workflow $instance */
            $instance = app($class);
            $instance->addGlobalMiddleware($middleware);
            $instance->setState(new WorkflowState($stateData));
            $instance->bootstrap();

            $resumeRequest = $checkpoint['interrupt']['request'] ?? null;
            $finalState = $resumeRequest !== null
                ? $instance->init($resumeRequest)->run()
                : $instance->init()->run();

            foreach ($middleware->steps as $step) {
                WorkflowTraceStep::create([
                    'workflow_trace_id' => $trace->id,
                    'node_id' => $step['node_id'],
                    'node_type' => $step['node_type'],
                    'state_snapshot' => $step['state_snapshot'] ?? null,
                    'duration_ms' => $step['duration_ms'] ?? null,
                ]);
            }

            $trace->update([
                'status' => 'completed',
                'output' => $this->outputWithNativeSteps($finalState->all(), $middleware->steps, $workflow),
                'checkpoint' => null,
                'awaiting_node_id' => null,
                'finished_at' => now(),
            ]);

            return $trace->fresh(['steps']);
        } catch (WorkflowInterrupt $interrupt) {
            return $this->pauseForNativeInterrupt($trace, $interrupt, $emitter);
        } catch (Throwable $exception) {
            $trace->update([
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
    protected function buildNativeStateData(int $workflowId, array $input): array
    {
        $message = (string) ($input['message'] ?? $input['input'] ?? '');
        $initialState = is_array($input['state'] ?? null) ? $input['state'] : [];

        $stateData = array_merge($initialState, [
            'input' => $message,
        ]);

        if (isset($input['thread_id']) && is_string($input['thread_id']) && $input['thread_id'] !== '') {
            $stateData['__studio_thread_id'] = ChatThreadKey::forWorkflow($workflowId, $input['thread_id']);
        }

        if (! empty($input['attachments']) && is_array($input['attachments'])) {
            $stateData['attachments'] = $input['attachments'];
        }

        return $stateData;
    }

    /** @param  array<string, mixed>  $input */
    protected function buildInitialState(GraphContext $graphContext, int $traceId, int $workflowId, array $input, ?callable $emitter): BuilderWorkflowState
    {
        $message = (string) ($input['message'] ?? $input['input'] ?? '');
        $initialState = is_array($input['state'] ?? null) ? $input['state'] : [];

        $stateData = array_merge($initialState, [
            'input' => $message,
            '__workflow_trace_id' => $traceId,
        ]);

        if (isset($input['thread_id']) && is_string($input['thread_id']) && $input['thread_id'] !== '') {
            $stateData['__studio_thread_id'] = ChatThreadKey::forWorkflow($workflowId, $input['thread_id']);
        }

        if (! empty($input['attachments']) && is_array($input['attachments'])) {
            $stateData['attachments'] = $input['attachments'];
        }

        $state = new BuilderWorkflowState($graphContext, $traceId, $stateData);

        if ($emitter !== null) {
            $state->stepEmitter = fn (string $event, array $data) => $emitter($event, array_merge($data, [
                'trace_id' => $traceId,
            ]));
        }

        return $state;
    }

    protected function finalizeTrace(WorkflowTrace $trace, BuilderWorkflowState $finalState): WorkflowTrace
    {
        $this->persistTraceSteps($trace, $finalState->get('__steps', []));

        $trace->update([
            'status' => 'completed',
            'output' => $finalState->all(),
            'checkpoint' => null,
            'awaiting_node_id' => null,
            'finished_at' => now(),
        ]);

        return $trace->fresh(['steps']);
    }

    protected function finalizeFailedTrace(
        WorkflowTrace $trace,
        ?BuilderWorkflowState $state,
        Throwable $exception,
    ): WorkflowTrace {
        if ($state !== null) {
            $this->persistTraceSteps($trace, $state->get('__steps', []));

            $trace->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'output' => $state->all(),
                'checkpoint' => null,
                'awaiting_node_id' => null,
                'finished_at' => now(),
            ]);
        } else {
            $trace->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
        }

        return $trace->fresh(['steps']);
    }

    /** @param  list<array<string, mixed>>  $steps */
    protected function persistTraceSteps(WorkflowTrace $trace, array $steps): void
    {
        if ($steps === []) {
            return;
        }

        if ($trace->steps()->exists()) {
            return;
        }

        foreach ($steps as $step) {
            WorkflowTraceStep::create([
                'workflow_trace_id' => $trace->id,
                'node_id' => $step['node_id'],
                'node_type' => $step['node_type'],
                'state_snapshot' => $step['state_snapshot'] ?? null,
                'duration_ms' => $step['duration_ms'] ?? null,
            ]);
        }
    }

    protected function pauseForHumanInput(
        WorkflowTrace $trace,
        HumanInputRequiredException $exception,
        ?BuilderWorkflowState $state,
        ?callable $emitter,
    ): WorkflowTrace {
        $trace->update([
            'status' => 'awaiting_input',
            'awaiting_node_id' => $exception->nodeId,
            'checkpoint' => [
                'state' => $state?->all() ?? [],
                'node_id' => $exception->nodeId,
                'output_key' => $exception->outputKey,
            ],
            'finished_at' => null,
        ]);

        if ($emitter !== null) {
            $emitter('human_input_required', [
                'trace_id' => $trace->id,
                'node_id' => $exception->nodeId,
                'prompt' => $exception->prompt,
                'output_key' => $exception->outputKey,
            ]);
        }

        return $trace->fresh(['steps']);
    }

    protected function pauseForParallelInterrupt(
        WorkflowTrace $trace,
        ParallelBranchInterruptException $exception,
        ?BuilderWorkflowState $state,
        ?callable $emitter,
    ): WorkflowTrace {
        $trace->update([
            'status' => 'awaiting_input',
            'awaiting_node_id' => $exception->pendingNodeId,
            'checkpoint' => [
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
                'trace_id' => $trace->id,
                'fork_id' => $exception->forkId,
                'branch_id' => $exception->branchId,
                'node_id' => $exception->pendingNodeId,
                'reason' => $exception->reason,
            ]);

            $emitter('human_input_required', [
                'trace_id' => $trace->id,
                'node_id' => $exception->pendingNodeId,
                'prompt' => $exception->prompt,
                'output_key' => $exception->outputKey,
            ]);
        }

        return $trace->fresh(['steps']);
    }

    protected function pauseForToolApproval(
        WorkflowTrace $trace,
        ToolApprovalRequiredException $exception,
        ?BuilderWorkflowState $state,
        ?callable $emitter,
    ): WorkflowTrace {
        $trace->update([
            'status' => 'awaiting_tool_approval',
            'awaiting_node_id' => $exception->nodeId,
            'checkpoint' => [
                'state' => $state?->all() ?? [],
                'node_id' => $exception->nodeId,
                'pending_tools' => $exception->pendingTools,
                'interrupt' => $exception->serializedInterrupt,
            ],
            'finished_at' => null,
        ]);

        if ($emitter !== null) {
            $emitter('tool_approval_required', [
                'trace_id' => $trace->id,
                'node_id' => $exception->nodeId,
                'pending_tools' => $exception->pendingTools,
                'message' => $exception->approvalMessage,
            ]);
        }

        return $trace->fresh(['steps']);
    }

    protected function pauseForNativeInterrupt(
        WorkflowTrace $trace,
        WorkflowInterrupt $interrupt,
        ?callable $emitter,
    ): WorkflowTrace {
        $request = $interrupt->getRequest();
        $node = $interrupt->getNode();
        $nodeId = defined($node::class.'::STUDIO_NODE_ID')
            ? (string) constant($node::class.'::STUDIO_NODE_ID')
            : 'human';

        $trace->update([
            'status' => 'awaiting_input',
            'awaiting_node_id' => $nodeId,
            'checkpoint' => [
                'state' => $interrupt->getState()->all(),
                'node_id' => $nodeId,
                'output_key' => 'human_response',
                'interrupt' => [
                    'workflow_id' => $interrupt->getWorkflowId(),
                    'request' => serialize($request),
                    'resume_token' => 'studio_trace_'.$trace->id,
                ],
            ],
            'finished_at' => null,
        ]);

        if ($emitter !== null) {
            $emitter('human_input_required', [
                'trace_id' => $trace->id,
                'node_id' => $nodeId,
                'prompt' => $request->getMessage(),
                'output_key' => 'human_response',
            ]);
        }

        return $trace->fresh(['steps']);
    }

    protected function recordHumanStep(
        WorkflowTrace $trace,
        string $nodeId,
        BuilderWorkflowState $state,
        string $outputKey,
        string $message,
    ): void {
        $state->emitStep('step_started', [
            'node_id' => $nodeId,
            'node_type' => 'human',
        ]);

        WorkflowTraceStep::create([
            'workflow_trace_id' => $trace->id,
            'node_id' => $nodeId,
            'node_type' => 'human',
            'state_snapshot' => array_merge($state->all(), [$outputKey => $message]),
            'duration_ms' => 0,
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
