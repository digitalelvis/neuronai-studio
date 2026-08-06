<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;
use NeuronAI\Workflow\WorkflowState;

class SetStateNodeExecutor implements NodeExecutorInterface
{
    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = $nodeConfig['data'] ?? [];
        $key = $data['key'] ?? 'value';
        $value = $data['value'] ?? null;

        // Legacy: whole-value copy (kept for existing graphs; not shown in Studio UI).
        if (($data['from_key'] ?? null) !== null && $data['from_key'] !== '') {
            $value = $state->get($data['from_key']);
        } elseif (($data['append_from_key'] ?? null) !== null && $data['append_from_key'] !== '') {
            // Legacy: append onto the current destination key.
            $append = $state->get($data['append_from_key']);
            $current = $state->get($key, '');
            $segments = array_filter([
                is_string($current) ? trim($current) : (string) $current,
                is_string($append) ? trim($append) : (is_scalar($append) ? (string) $append : ''),
            ], fn (string $segment) => $segment !== '');

            $value = implode("\n", $segments);
        } elseif (is_string($value)) {
            $value = StateTemplateInterpolator::interpolate($value, $state);
        }

        $state->set($key, $value);

        return 'default';
    }
}
