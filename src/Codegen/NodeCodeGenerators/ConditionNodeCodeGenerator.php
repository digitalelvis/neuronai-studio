<?php

namespace ElvisLopesDigital\NeuronAIStudio\Codegen\NodeCodeGenerators;

class ConditionNodeCodeGenerator implements NodeCodeGeneratorInterface
{
    public function supports(string $type): bool
    {
        return $type === 'condition';
    }

    public function generate(array $nodePlan, CodegenContext $context): array
    {
        $data = $nodePlan['data'];
        $key = var_export((string) ($data['state_key'] ?? 'input'), true);
        $operator = (string) ($data['operator'] ?? 'not_empty');
        $value = $context->exporter->exportValue($data['value'] ?? null, 2);
        $branchReturns = $nodePlan['branchReturns'];

        $trueReturn = $context->returnStatement('', 'true', $branchReturns);
        $falseReturn = $context->returnStatement('', 'false', $branchReturns);

        $condition = match ($operator) {
            'equals' => "\$stateValue == {$value}",
            'not_equals' => "\$stateValue != {$value}",
            'contains' => "is_string(\$stateValue) && str_contains(\$stateValue, (string) {$value})",
            'empty' => 'empty($stateValue)',
            default => '! empty($stateValue)',
        };

        $body = <<<PHP
        \$stateValue = \$state->get({$key});

        if ({$condition}) {
            {$trueReturn}
        }

        {$falseReturn}
PHP;

        return ['body' => $body, 'imports' => []];
    }
}
