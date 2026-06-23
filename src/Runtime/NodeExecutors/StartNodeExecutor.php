<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime\NodeExecutors;

use ElvisLopesDigital\NeuronAIStudio\Runtime\GraphContext;
use NeuronAI\Workflow\WorkflowState;

class StartNodeExecutor implements NodeExecutorInterface
{
    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        return 'default';
    }
}
