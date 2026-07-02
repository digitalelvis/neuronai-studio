<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowStateValue;
use NeuronAI\Workflow\WorkflowState;

class LoopNodeExecutor implements NodeExecutorInterface
{
    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = $nodeConfig['data'] ?? [];
        $nodeId = (string) ($nodeConfig['id'] ?? 'loop');
        $iterationKey = "__loop_iterations.{$nodeId}";
        $iterations = (int) $state->get($iterationKey, 0) + 1;
        $maxSteps = (int) ($data['max_steps'] ?? config('neuronai-studio.loop.default_max_steps', 10));

        $state->set($iterationKey, $iterations);

        if ($iterations >= $maxSteps) {
            return 'exit';
        }

        if (($data['state_key'] ?? null) === null) {
            return 'continue';
        }

        return $this->conditionMatches($data, $state) ? 'exit' : 'continue';
    }

    /** @param array<string, mixed> $data */
    protected function conditionMatches(array $data, WorkflowState $state): bool
    {
        $key = (string) ($data['state_key'] ?? 'input');
        $operator = $data['operator'] ?? 'not_empty';
        $value = $data['value'] ?? null;
        $stateValue = WorkflowStateValue::get($state, $key);

        return match ($operator) {
            'equals' => $stateValue == $value,
            'not_equals' => $stateValue != $value,
            'contains' => is_string($stateValue) && str_contains($stateValue, (string) $value),
            'empty' => empty($stateValue),
            default => ! empty($stateValue),
        };
    }
}
