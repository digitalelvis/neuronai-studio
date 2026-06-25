<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime;

use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowDefinition;
use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowTrace;
use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowTraceStep;
use ElvisLopesDigital\NeuronAIStudio\Runtime\Exceptions\HumanInputRequiredException;
use ElvisLopesDigital\NeuronAIStudio\Support\ChatThreadKey;
use Throwable;

class WorkflowRunner
{
    public function __construct(
        protected GraphValidator $validator,
        protected GraphExecutionLoop $executionLoop,
    ) {}

    /** @param  array<string, mixed>  $input */
    public function run(WorkflowDefinition $workflow, array $input = [], ?callable $emitter = null): WorkflowTrace
    {
        $this->validator->assertValid($workflow->graph);

        $trace = WorkflowTrace::create([
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
        } catch (Throwable $exception) {
            $trace->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            throw $exception;
        }
    }

    public function resume(WorkflowTrace $trace, string $nodeId, string $message, ?callable $emitter = null): WorkflowTrace
    {
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
        } catch (Throwable $exception) {
            $trace->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            throw $exception;
        }
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
        $steps = $finalState->get('__steps', []);
        foreach ($steps as $step) {
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
            'output' => $finalState->all(),
            'checkpoint' => null,
            'awaiting_node_id' => null,
            'finished_at' => now(),
        ]);

        return $trace->fresh(['steps']);
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
}
