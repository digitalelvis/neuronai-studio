<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use NeuronAI\Workflow\WorkflowState;

class DelayNodeExecutor implements NodeExecutorInterface
{
    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = $nodeConfig['data'] ?? [];
        $seconds = (int) ($data['seconds'] ?? 1);

        if ($seconds > 0 && $seconds <= 5) {
            sleep($seconds);
        }

        $state->set('last_delay_seconds', $seconds);

        return 'default';
    }
}
