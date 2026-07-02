<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
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

        if (($data['append_from_key'] ?? null) !== null) {
            $append = $state->get($data['append_from_key']);
            $current = $state->get($key, '');
            $segments = array_filter([
                is_string($current) ? trim($current) : (string) $current,
                is_string($append) ? trim($append) : (is_scalar($append) ? (string) $append : ''),
            ], fn (string $segment) => $segment !== '');

            $value = implode("\n", $segments);
        }

        $state->set($key, $value);

        return 'default';
    }
}
