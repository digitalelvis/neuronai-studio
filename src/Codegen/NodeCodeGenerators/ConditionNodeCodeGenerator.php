<?php

namespace DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators;

use DigitalElvis\NeuronAIStudio\Runtime\ConditionEvaluator;

class ConditionNodeCodeGenerator implements NodeCodeGeneratorInterface
{
    public function supports(string $type): bool
    {
        return $type === 'condition';
    }

    public function generate(array $nodePlan, CodegenContext $context): array
    {
        $data = $nodePlan['data'];
        $branchReturns = $nodePlan['branchReturns'];
        $trueReturn = $context->returnStatement('', 'true', $branchReturns);
        $falseReturn = $context->returnStatement('', 'false', $branchReturns);

        if ($this->hasCompoundRules($data)) {
            return $this->generateCompound($data, $context, $trueReturn, $falseReturn);
        }

        $key = var_export((string) ($data['state_key'] ?? 'input'), true);
        $operator = (string) ($data['operator'] ?? 'not_empty');
        $valueType = (string) ($data['value_type'] ?? ConditionEvaluator::VALUE_TYPE_AUTO);
        $strict = (bool) ($data['strict'] ?? false);
        $value = $context->exporter->exportValue($data['value'] ?? null, 2);
        $normalizedValue = $this->exportNormalizedValue($context, $data['value'] ?? null, $valueType);
        $condition = ConditionEvaluator::buildCodegenCondition('$stateValue', $operator, $normalizedValue, $valueType, $strict);

        $body = <<<PHP
        \$stateValue = \\DigitalElvis\\NeuronAIStudio\\Runtime\\WorkflowStateValue::get(\$state, {$key});

        if ({$condition}) {
            {$trueReturn}
        }

        {$falseReturn}
PHP;

        return ['body' => $body, 'imports' => []];
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
    protected function generateCompound(array $data, CodegenContext $context, string $trueReturn, string $falseReturn): array
    {
        $logic = ($data['logic'] ?? 'all') === 'any' ? 'any' : 'all';
        $rules = array_values(array_filter($data['rules'], static fn ($rule) => is_array($rule)));

        if ($rules === []) {
            return ['body' => $falseReturn, 'imports' => []];
        }

        $checks = [];
        foreach ($rules as $rule) {
            $key = var_export((string) ($rule['state_key'] ?? 'input'), true);
            $operator = (string) ($rule['operator'] ?? 'not_empty');
            $valueType = (string) ($rule['value_type'] ?? ConditionEvaluator::VALUE_TYPE_AUTO);
            $strict = (bool) ($rule['strict'] ?? false);
            $normalizedValue = $this->exportNormalizedValue($context, $rule['value'] ?? null, $valueType);
            $condition = ConditionEvaluator::buildCodegenCondition(
                "\\DigitalElvis\\NeuronAIStudio\\Runtime\\WorkflowStateValue::get(\$state, {$key})",
                $operator,
                $normalizedValue,
                $valueType,
                $strict,
            );
            $checks[] = $condition;
        }

        $joined = $logic === 'any'
            ? implode(' || ', $checks)
            : implode(' && ', $checks);

        $body = <<<PHP
        if ({$joined}) {
            {$trueReturn}
        }

        {$falseReturn}
PHP;

        return ['body' => $body, 'imports' => []];
    }

    protected function exportNormalizedValue(CodegenContext $context, mixed $value, string $valueType): string
    {
        $evaluator = new ConditionEvaluator;
        $normalized = $evaluator->normalizeConditionValue($value, $valueType);

        return $context->exporter->exportValue($normalized, 2);
    }
}
