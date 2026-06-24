<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime;

use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowDefinition;
use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowRun;
use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowRunStep;
use ElvisLopesDigital\NeuronAIStudio\Runtime\Exceptions\HumanInputRequiredException;
use Throwable;

class WorkflowRunner
{
    public function __construct(
        protected GraphValidator $validator,
        protected GraphExecutionLoop $executionLoop,
    ) {}

    /** @param  array<string, mixed>  $input */
    public function run(WorkflowDefinition $workflow, array $input = [], ?callable $emitter = null): WorkflowRun
    {
        $this->validator->assertValid($workflow->graph);

        $run = WorkflowRun::create([
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

            $state = $this->buildInitialState($graphContext, $run->id, $input, $emitter);

            $interpreter = new GraphInterpreterWorkflow($graphContext, $state);
            $interpreter->bootstrap();

            $finalState = $interpreter->init()->run();

            return $this->finalizeRun($run, $finalState);
        } catch (HumanInputRequiredException $exception) {
            return $this->pauseForHumanInput($run, $exception, $state, $emitter);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            throw $exception;
        }
    }

    public function resume(WorkflowRun $run, string $nodeId, string $message, ?callable $emitter = null): WorkflowRun
    {
        $workflow = $run->workflow ?? WorkflowDefinition::findOrFail($run->workflow_definition_id);
        $checkpoint = $run->checkpoint ?? [];
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

        $state = new BuilderWorkflowState($graphContext, $run->id, $stateData);

        if ($emitter !== null) {
            $state->stepEmitter = fn (string $event, array $data) => $emitter($event, array_merge($data, [
                'run_id' => $run->id,
            ]));
        }

        $run->update([
            'status' => 'running',
            'awaiting_node_id' => null,
        ]);

        try {
            $nextNodeId = $graphContext->targetForHandle($nodeId) ?? '';
            $this->recordHumanStep($run, $nodeId, $state, $outputKey, $message);

            if ($nextNodeId === '') {
                $run->update([
                    'status' => 'completed',
                    'output' => $state->all(),
                    'checkpoint' => null,
                    'finished_at' => now(),
                ]);

                return $run->fresh(['steps']);
            }

            $state->set('__current_node_id', $nextNodeId);
            $finalState = $this->executionLoop->runFromNode($nextNodeId, $graphContext, $state);

            return $this->finalizeRun($run, $finalState);
        } catch (HumanInputRequiredException $exception) {
            return $this->pauseForHumanInput($run, $exception, $state, $emitter);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            throw $exception;
        }
    }

    /** @param  array<string, mixed>  $input */
    protected function buildInitialState(GraphContext $graphContext, int $runId, array $input, ?callable $emitter): BuilderWorkflowState
    {
        $message = (string) ($input['message'] ?? $input['input'] ?? '');
        $initialState = is_array($input['state'] ?? null) ? $input['state'] : [];

        $stateData = array_merge($initialState, [
            'input' => $message,
            '__workflow_run_id' => $runId,
        ]);

        if (! empty($input['attachments']) && is_array($input['attachments'])) {
            $stateData['attachments'] = $input['attachments'];
        }

        $state = new BuilderWorkflowState($graphContext, $runId, $stateData);

        if ($emitter !== null) {
            $state->stepEmitter = fn (string $event, array $data) => $emitter($event, array_merge($data, [
                'run_id' => $runId,
            ]));
        }

        return $state;
    }

    protected function finalizeRun(WorkflowRun $run, BuilderWorkflowState $finalState): WorkflowRun
    {
        $steps = $finalState->get('__steps', []);
        foreach ($steps as $step) {
            WorkflowRunStep::create([
                'workflow_run_id' => $run->id,
                'node_id' => $step['node_id'],
                'node_type' => $step['node_type'],
                'state_snapshot' => $step['state_snapshot'] ?? null,
                'duration_ms' => $step['duration_ms'] ?? null,
            ]);
        }

        $run->update([
            'status' => 'completed',
            'output' => $finalState->all(),
            'checkpoint' => null,
            'awaiting_node_id' => null,
            'finished_at' => now(),
        ]);

        return $run->fresh(['steps']);
    }

    protected function pauseForHumanInput(
        WorkflowRun $run,
        HumanInputRequiredException $exception,
        ?BuilderWorkflowState $state,
        ?callable $emitter,
    ): WorkflowRun {
        $run->update([
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
                'run_id' => $run->id,
                'node_id' => $exception->nodeId,
                'prompt' => $exception->prompt,
                'output_key' => $exception->outputKey,
            ]);
        }

        return $run->fresh(['steps']);
    }

    protected function recordHumanStep(
        WorkflowRun $run,
        string $nodeId,
        BuilderWorkflowState $state,
        string $outputKey,
        string $message,
    ): void {
        $state->emitStep('step_started', [
            'node_id' => $nodeId,
            'node_type' => 'human',
        ]);

        WorkflowRunStep::create([
            'workflow_run_id' => $run->id,
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
