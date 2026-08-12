<?php

namespace DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators;

use DigitalElvis\NeuronAIStudio\Runtime\ConditionEvaluator;

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
        $valueType = (string) ($data['value_type'] ?? ConditionEvaluator::VALUE_TYPE_AUTO);
        $strict = (bool) ($data['strict'] ?? false);
        $branchReturns = $nodePlan['branchReturns'];

        $maxSteps = isset($data['max_steps'])
            ? max(1, (int) $data['max_steps'])
            : max(1, (int) config('neuronai-studio.loop.default_max_steps', 10));
        $maxStepsExpr = var_export($maxSteps, true);

        $continueReturn = $context->returnStatement('', 'continue', $branchReturns);
        $exitReturn = $context->returnStatement('', 'exit', $branchReturns);

        $evaluator = new ConditionEvaluator;
        $normalizedValue = $context->exporter->exportValue(
            $evaluator->normalizeConditionValue($data['value'] ?? null, $valueType),
            2,
        );
        $condition = ConditionEvaluator::buildCodegenCondition(
            '$stateValue',
            $operator,
            $normalizedValue,
            $valueType,
            $strict,
        );

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

        \$stateValue = \\DigitalElvis\\NeuronAIStudio\\Runtime\\WorkflowStateValue::get(\$state, {$key});

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
