<?php

namespace DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators;

class LoopNodeCodeGenerator implements NodeCodeGeneratorInterface
{
    public function supports(string $type): bool
    {
        return $type === 'loop';
    }

    public function generate(array $nodePlan, CodegenContext $context): array
    {
        $data = $nodePlan['data'];
        $nodeId = var_export((string) $nodePlan['id'], true);
        $key = var_export((string) ($data['state_key'] ?? 'input'), true);
        $operator = (string) ($data['operator'] ?? 'not_empty');
        $value = $context->exporter->exportValue($data['value'] ?? null, 2);
        $branchReturns = $nodePlan['branchReturns'];

        $maxSteps = isset($data['max_steps'])
            ? max(1, (int) $data['max_steps'])
            : max(1, (int) config('neuronai-studio.loop.default_max_steps', 10));
        $maxStepsExpr = var_export($maxSteps, true);

        $continueReturn = $context->returnStatement('', 'continue', $branchReturns);
        $exitReturn = $context->returnStatement('', 'exit', $branchReturns);

        $condition = match ($operator) {
            'equals' => "\$stateValue == {$value}",
            'not_equals' => "\$stateValue != {$value}",
            'contains' => "is_string(\$stateValue) && str_contains(\$stateValue, (string) {$value})",
            'empty' => 'empty($stateValue)',
            default => '! empty($stateValue)',
        };

        $body = <<<PHP
        \$iterationKey = "__loop_iterations.{$nodeId}";
        \$iterations = (int) \$state->get(\$iterationKey, 0) + 1;
        \$maxSteps = {$maxStepsExpr};

        \$state->set(\$iterationKey, \$iterations);

        \$allIterations = \$state->get('__loop_iterations', []);
        if (! is_array(\$allIterations)) {
            \$allIterations = [];
        }
        \$allIterations[{$nodeId}] = \$iterations;
        \$state->set('__loop_iterations', \$allIterations);

        if (\$iterations > \$maxSteps) {
            throw new MaxLoopIterationsException({$nodeId}, \$iterations, \$maxSteps);
        }

        \$stateValue = \$state->get({$key});

        if ({$condition}) {
            {$exitReturn}
        }

        {$continueReturn}
PHP;

        return [
            'body' => $body,
            'imports' => [
                'DigitalElvis\\NeuronAIStudio\\Runtime\\Exceptions\\MaxLoopIterationsException',
            ],
        ];
    }
}
