<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Nodes;

use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\Events\GraphStepEvent;
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

        if ($state instanceof BuilderWorkflowState) {
            $firstConfig = $this->graphContext->nodeConfig($firstTarget);
            $state->emitStep('step_started', [
                'node_id' => $firstTarget,
                'node_type' => $firstConfig['type'] ?? 'unknown',
            ]);
        }

        return new GraphStepEvent($firstTarget);
    }
}
