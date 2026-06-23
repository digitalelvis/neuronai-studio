<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime\Nodes;

use ElvisLopesDigital\NeuronAIStudio\Runtime\BuilderWorkflowState;
use ElvisLopesDigital\NeuronAIStudio\Runtime\GraphContext;
use ElvisLopesDigital\NeuronAIStudio\Runtime\Events\GraphStepEvent;
use ElvisLopesDigital\NeuronAIStudio\Runtime\NodeExecutors\NodeExecutorRegistry;
use NeuronAI\Workflow\Events\StopEvent;
use NeuronAI\Workflow\Node;
use NeuronAI\Workflow\WorkflowState;

class GraphStepExecutorNode extends Node
{
    public function __construct(
        protected GraphContext $graphContext,
        protected ?NodeExecutorRegistry $executors = null,
    ) {
        $this->executors ??= app(NodeExecutorRegistry::class);
    }

    public function __invoke(GraphStepEvent $event, WorkflowState $state): GraphStepEvent|StopEvent
    {
        $nodeId = $event->nodeId;
        $nodeConfig = $this->graphContext->nodeConfig($nodeId);
        $nodeType = $nodeConfig['type'] ?? 'unknown';

        $startedAt = microtime(true);

        if ($nodeType === 'stop') {
            return new StopEvent($state->all());
        }

        $handle = $this->executors->execute($nodeType, $nodeConfig, $state, $this->graphContext);

        $this->recordStep($state, $nodeId, $nodeType, $startedAt);

        if ($nodeType === 'stop') {
            return new StopEvent($state->all());
        }

        $nextNodeId = $this->graphContext->targetForHandle($nodeId, $handle);

        if (! $nextNodeId) {
            return new StopEvent($state->all());
        }

        $state->set('__current_node_id', $nextNodeId);

        return new GraphStepEvent($nextNodeId);
    }

    protected function recordStep(WorkflowState $state, string $nodeId, string $nodeType, float $startedAt): void
    {
        if (! $state instanceof BuilderWorkflowState) {
            return;
        }

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
