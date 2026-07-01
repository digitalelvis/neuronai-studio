<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\MaxLoopIterationsException;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\NodeExecutorRegistry;
use RuntimeException;

class GraphExecutionLoop
{
    public function __construct(
        protected NodeExecutorRegistry $executors,
    ) {}

    public function runFromNode(
        string $nodeId,
        GraphContext $graphContext,
        BuilderWorkflowState $state,
    ): BuilderWorkflowState {
        $globalMaxSteps = max(1, (int) config('neuronai-studio.loop.global_max_steps', 1000));
        $executedSteps = 0;

        while ($nodeId !== '') {
            $executedSteps++;

            if ($executedSteps > $globalMaxSteps) {
                throw new RuntimeException(
                    "Workflow execution exceeded global max steps ({$globalMaxSteps}).",
                );
            }

            $nodeConfig = $graphContext->nodeConfig($nodeId);
            if ($nodeConfig === []) {
                break;
            }

            $nodeConfig['id'] = $nodeId;
            $nodeType = (string) ($nodeConfig['type'] ?? 'unknown');
            $startedAt = microtime(true);

            $iteration = null;
            if ($nodeType === 'loop') {
                $iteration = (int) $state->get("__loop_iterations.{$nodeId}", 0) + 1;
            }

            $state->emitStep('step_started', [
                'node_id' => $nodeId,
                'node_type' => $nodeType,
                'iteration' => $iteration,
            ]);

            if ($nodeType === 'stop') {
                $state->emitStep('step_completed', [
                    'node_id' => $nodeId,
                    'node_type' => $nodeType,
                    'handle' => 'default',
                    'duration_ms' => 0,
                ]);
                break;
            }

            try {
                $handle = $this->executors->execute($nodeType, $nodeConfig, $state, $graphContext);
            } catch (MaxLoopIterationsException $exception) {
                $state->emitStep('trace_failed', [
                    'message' => $exception->getMessage(),
                    'node_id' => $exception->nodeId,
                    'iteration' => $exception->iteration,
                    'max_steps' => $exception->maxSteps,
                ]);

                throw $exception;
            }

            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

            $this->recordStep($state, $nodeId, $nodeType, $startedAt);

            $completedPayload = [
                'node_id' => $nodeId,
                'node_type' => $nodeType,
                'handle' => $handle,
                'duration_ms' => $durationMs,
            ];

            if ($nodeType === 'loop') {
                $completedPayload['iteration'] = (int) $state->get("__loop_iterations.{$nodeId}", 0);
            }

            $state->emitStep('step_completed', $completedPayload);

            $nextNodeId = $graphContext->targetForHandle($nodeId, $handle) ?? '';
            $state->set('__current_node_id', $nextNodeId);
            $nodeId = $nextNodeId;
        }

        return $state;
    }

    protected function recordStep(BuilderWorkflowState $state, string $nodeId, string $nodeType, float $startedAt): void
    {
        $steps = $state->get('__steps', []);
        $steps[] = [
            'node_id' => $nodeId,
            'node_type' => $nodeType,
            'state_snapshot' => $state->all(),
            'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ];
        $state->set('__steps', $steps);
    }
}
