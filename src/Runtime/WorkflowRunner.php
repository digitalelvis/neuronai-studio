<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime;

use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowDefinition;
use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowRun;
use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowRunStep;
use Throwable;

class WorkflowRunner
{
    public function __construct(
        protected GraphValidator $validator,
    ) {}

    public function run(WorkflowDefinition $workflow, array $input = []): WorkflowRun
    {
        $this->validator->assertValid($workflow->graph);

        $run = WorkflowRun::create([
            'workflow_definition_id' => $workflow->id,
            'status' => 'running',
            'input' => $input,
            'started_at' => now(),
        ]);

        try {
            $graphContext = new GraphContext(
                $workflow->graph['nodes'] ?? [],
                $workflow->graph['edges'] ?? [],
            );

            $state = new BuilderWorkflowState($graphContext, $run->id, [
                'input' => $input['message'] ?? $input['input'] ?? '',
                '__workflow_run_id' => $run->id,
            ]);

            $interpreter = new GraphInterpreterWorkflow($graphContext, $state);
            $interpreter->bootstrap();

            $finalState = $interpreter->init()->run();

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
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            throw $exception;
        }

        return $run->fresh(['steps']);
    }
}
