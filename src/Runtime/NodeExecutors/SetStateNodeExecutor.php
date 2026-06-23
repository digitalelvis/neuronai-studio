<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime\NodeExecutors;

use ElvisLopesDigital\NeuronAIStudio\Runtime\GraphContext;
use NeuronAI\Workflow\WorkflowState;

class SetStateNodeExecutor implements NodeExecutorInterface
{
    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = $nodeConfig['data'] ?? [];
        $key = $data['key'] ?? 'value';
        $value = $data['value'] ?? null;

        if (($data['from_key'] ?? null) !== null) {
            $value = $state->get($data['from_key']);
        }

        $state->set($key, $value);

        return 'default';
    }
}
