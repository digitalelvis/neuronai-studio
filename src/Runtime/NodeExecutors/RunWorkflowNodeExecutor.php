<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use NeuronAI\Workflow\WorkflowState;
use RuntimeException;

/**
 * Stub registered in EW-T1; Step Mode execution lands in EW-T6.
 */
class RunWorkflowNodeExecutor implements NodeExecutorInterface
{
    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        throw new RuntimeException(
            'run_workflow Step Mode execution is not implemented yet.'
        );
    }
}
