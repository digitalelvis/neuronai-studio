<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\NodeExecutorRegistry;
use NeuronAI\Workflow\WorkflowState;

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
        while ($nodeId !== '') {
            $nodeConfig = $graphContext->nodeConfig($nodeId);
            if ($nodeConfig === []) {
                break;
            }

            $nodeConfig['id'] = $nodeId;
            $nodeType = (string) ($nodeConfig['type'] ?? 'unknown');
            $startedAt = microtime(true);

            $state->emitStep('step_started', [
                'node_id' => $nodeId,
                'node_type' => $nodeType,
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

            $handle = $this->executors->execute($nodeType, $nodeConfig, $state, $graphContext);
            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

            $this->recordStep($state, $nodeId, $nodeType, $startedAt);

            $state->emitStep('step_completed', [
                'node_id' => $nodeId,
                'node_type' => $nodeType,
                'handle' => $handle,
                'duration_ms' => $durationMs,
            ]);

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
