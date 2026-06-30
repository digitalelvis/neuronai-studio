<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use DigitalElvis\NeuronAIStudio\Runtime\Events\GraphStepEvent;
use DigitalElvis\NeuronAIStudio\Runtime\Nodes\GraphBootstrapNode;
use DigitalElvis\NeuronAIStudio\Runtime\Nodes\GraphStepExecutorNode;
use NeuronAI\Workflow\Workflow;
use NeuronAI\Workflow\WorkflowState;

class GraphInterpreterWorkflow extends Workflow
{
    public function __construct(
        protected GraphContext $graphContext,
        ?WorkflowState $state = null,
    ) {
        parent::__construct(state: $state ?? new BuilderWorkflowState($graphContext));
    }

    protected function nodes(): array
    {
        return [
            new GraphBootstrapNode($this->graphContext),
            new GraphStepExecutorNode($this->graphContext),
        ];
    }

    public function resolveState(): BuilderWorkflowState
    {
        $state = parent::resolveState();

        if (! $state instanceof BuilderWorkflowState) {
            throw new \RuntimeException('Expected BuilderWorkflowState.');
        }

        return $state;
    }
}
