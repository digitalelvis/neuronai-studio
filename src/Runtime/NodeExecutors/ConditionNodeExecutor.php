<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use NeuronAI\Workflow\WorkflowState;

class ConditionNodeExecutor implements NodeExecutorInterface
{
    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = $nodeConfig['data'] ?? [];
        $key = $data['state_key'] ?? 'input';
        $operator = $data['operator'] ?? 'not_empty';
        $value = $data['value'] ?? null;
        $stateValue = $state->get($key);

        $result = match ($operator) {
            'equals' => $stateValue == $value,
            'not_equals' => $stateValue != $value,
            'contains' => is_string($stateValue) && str_contains($stateValue, (string) $value),
            'empty' => empty($stateValue),
            default => ! empty($stateValue),
        };

        return $result ? 'true' : 'false';
    }
}
