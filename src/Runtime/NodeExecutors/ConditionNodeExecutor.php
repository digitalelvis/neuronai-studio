<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Runtime\ConditionEvaluator;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use NeuronAI\Workflow\WorkflowState;

class ConditionNodeExecutor implements NodeExecutorInterface
{
    public function __construct(
        protected ConditionEvaluator $evaluator = new ConditionEvaluator,
    ) {}

    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = $nodeConfig['data'] ?? [];

        $result = $this->evaluateNodeData($data, $state);

        return $result ? 'true' : 'false';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function evaluateNodeData(array $data, WorkflowState $state): bool
    {
        if ($this->hasCompoundRules($data)) {
            return $this->evaluateCompoundRules($data, $state);
        }

        return $this->evaluator->evaluateRule([
            'state_key' => $data['state_key'] ?? 'input',
            'operator' => $data['operator'] ?? 'not_empty',
            'value' => $data['value'] ?? null,
            'value_type' => $data['value_type'] ?? ConditionEvaluator::VALUE_TYPE_AUTO,
            'strict' => $data['strict'] ?? false,
        ], $state);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function hasCompoundRules(array $data): bool
    {
        return isset($data['rules']) && is_array($data['rules']) && $data['rules'] !== [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function evaluateCompoundRules(array $data, WorkflowState $state): bool
    {
        $logic = ($data['logic'] ?? 'all') === 'any' ? 'any' : 'all';
        $rules = array_values(array_filter(
            $data['rules'],
            static fn ($rule) => is_array($rule),
        ));

        if ($rules === []) {
            return false;
        }

        if ($logic === 'any') {
            foreach ($rules as $rule) {
                if ($this->evaluator->evaluateRule($rule, $state)) {
                    return true;
                }
            }

            return false;
        }

        foreach ($rules as $rule) {
            if (! $this->evaluator->evaluateRule($rule, $state)) {
                return false;
            }
        }

        return true;
    }
}
