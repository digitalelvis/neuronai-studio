<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime\Nodes;

use ElvisLopesDigital\NeuronAIStudio\Runtime\GraphContext;
use ElvisLopesDigital\NeuronAIStudio\Runtime\Events\GraphStepEvent;
use NeuronAI\Workflow\Events\StartEvent;
use NeuronAI\Workflow\Events\StopEvent;
use NeuronAI\Workflow\Node;
use NeuronAI\Workflow\WorkflowState;

class GraphBootstrapNode extends Node
{
    public function __construct(
        protected GraphContext $graphContext,
    ) {}

    public function __invoke(StartEvent $event, WorkflowState $state): GraphStepEvent|StopEvent
    {
        $startNode = null;
        foreach ($this->graphContext->nodes as $node) {
            if (($node['type'] ?? '') === 'start') {
                $startNode = $node;
                break;
            }
        }

        if (! $startNode) {
            return new StopEvent(['error' => 'No start node found.']);
        }

        $firstTarget = $this->graphContext->targetForHandle($startNode['id']);

        if (! $firstTarget) {
            return new StopEvent($state->all());
        }

        $state->set('__current_node_id', $firstTarget);

        return new GraphStepEvent($firstTarget);
    }
}
