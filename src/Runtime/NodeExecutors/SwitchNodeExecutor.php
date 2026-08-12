<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Runtime\ConditionEvaluator;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use NeuronAI\Workflow\WorkflowState;

class SwitchNodeExecutor implements NodeExecutorInterface
{
    public function __construct(
        protected ConditionEvaluator $evaluator = new ConditionEvaluator,
    ) {}

    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = $nodeConfig['data'] ?? [];
        $cases = self::normalizeCases(is_array($data['cases'] ?? null) ? $data['cases'] : []);

        foreach ($cases as $case) {
            if ($this->evaluator->evaluateRule($case, $state)) {
                return (string) $case['id'];
            }
        }

        return 'default';
    }

    /**
     * @param  array<int, mixed>  $cases
     * @return array<string, array<string, mixed>>
     */
    public static function normalizeCases(array $cases): array
    {
        $normalized = [];

        foreach ($cases as $index => $case) {
            if (! is_array($case)) {
                continue;
            }

            $id = trim((string) ($case['id'] ?? ''));
            if ($id === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $id)) {
                continue;
            }

            if (isset($normalized[$id])) {
                continue;
            }

            $normalized[$id] = [
                'id' => $id,
                'label' => trim((string) ($case['label'] ?? $id)) ?: $id,
                'state_key' => (string) ($case['state_key'] ?? 'input'),
                'operator' => (string) ($case['operator'] ?? 'not_empty'),
                'value' => $case['value'] ?? null,
                'value_type' => (string) ($case['value_type'] ?? ConditionEvaluator::VALUE_TYPE_AUTO),
                'strict' => (bool) ($case['strict'] ?? false),
            ];
        }

        return $normalized;
    }
}
