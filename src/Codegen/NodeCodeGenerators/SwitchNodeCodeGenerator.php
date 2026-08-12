<?php

namespace DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators;

use DigitalElvis\NeuronAIStudio\Runtime\ConditionEvaluator;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\SwitchNodeExecutor;

class SwitchNodeCodeGenerator implements NodeCodeGeneratorInterface
{
    public function supports(string $type): bool
    {
        return $type === 'switch';
    }

    public function generate(array $nodePlan, CodegenContext $context): array
    {
        $data = $nodePlan['data'];
        $branchReturns = $nodePlan['branchReturns'];
        $cases = SwitchNodeExecutor::normalizeCases(is_array($data['cases'] ?? null) ? $data['cases'] : []);
        $defaultReturn = $context->returnStatement('', 'default', $branchReturns);

        if ($cases === []) {
            return ['body' => $defaultReturn, 'imports' => []];
        }

        $lines = [];
        $first = true;

        foreach ($cases as $case) {
            $key = var_export((string) ($case['state_key'] ?? 'input'), true);
            $operator = (string) ($case['operator'] ?? 'not_empty');
            $valueType = (string) ($case['value_type'] ?? ConditionEvaluator::VALUE_TYPE_AUTO);
            $strict = (bool) ($case['strict'] ?? false);
            $evaluator = new ConditionEvaluator;
            $normalizedValue = $context->exporter->exportValue(
                $evaluator->normalizeConditionValue($case['value'] ?? null, $valueType),
                2,
            );
            $condition = ConditionEvaluator::buildCodegenCondition(
                "\\DigitalElvis\\NeuronAIStudio\\Runtime\\WorkflowStateValue::get(\$state, {$key})",
                $operator,
                $normalizedValue,
                $valueType,
                $strict,
            );
            $branch = $context->returnStatement('', (string) $case['id'], $branchReturns);
            $keyword = $first ? 'if' : 'elseif';
            $first = false;
            $lines[] = "        {$keyword} ({$condition}) {";
            $lines[] = "            {$branch}";
            $lines[] = '        }';
        }

        $lines[] = '';
        $lines[] = "        {$defaultReturn}";

        return ['body' => implode("\n", $lines), 'imports' => []];
    }
}
