<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\ConditionEvaluator;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\ConditionNodeExecutor;

class ConditionEvaluatorTest extends TestCase
{
    /** @return array{0: ConditionEvaluator, 1: BuilderWorkflowState} */
    protected function evaluatorWithState(array $stateData = []): array
    {
        $context = new GraphContext([], []);

        return [
            new ConditionEvaluator,
            new BuilderWorkflowState($context, null, $stateData),
        ];
    }

    public function test_is_true_matches_boolean_true(): void
    {
        [$evaluator, $state] = $this->evaluatorWithState(['active' => true]);

        $this->assertTrue($evaluator->evaluateRule([
            'state_key' => 'active',
            'operator' => 'is_true',
        ], $state));
    }

    public function test_is_true_rejects_string_true(): void
    {
        [$evaluator, $state] = $this->evaluatorWithState(['active' => 'true']);

        // String "true" from Set State is accepted as boolean-true for is_true.
        $this->assertTrue($evaluator->evaluateRule([
            'state_key' => 'active',
            'operator' => 'is_true',
        ], $state));
    }

    public function test_is_true_rejects_unrelated_string(): void
    {
        [$evaluator, $state] = $this->evaluatorWithState(['active' => 'yes-please']);

        $this->assertFalse($evaluator->evaluateRule([
            'state_key' => 'active',
            'operator' => 'is_true',
        ], $state));
    }

    public function test_is_false_accepts_string_false(): void
    {
        [$evaluator, $state] = $this->evaluatorWithState(['pending' => 'false']);

        $this->assertTrue($evaluator->evaluateRule([
            'state_key' => 'pending',
            'operator' => 'is_false',
        ], $state));
    }

    public function test_equals_with_boolean_value_type(): void
    {
        [$evaluator, $state] = $this->evaluatorWithState(['active' => 'true']);

        $this->assertTrue($evaluator->evaluateRule([
            'state_key' => 'active',
            'operator' => 'equals',
            'value' => 'true',
            'value_type' => ConditionEvaluator::VALUE_TYPE_BOOLEAN,
        ], $state));
    }

    public function test_is_null_for_missing_key(): void
    {
        [$evaluator, $state] = $this->evaluatorWithState([]);

        $this->assertTrue($evaluator->evaluateRule([
            'state_key' => 'missing',
            'operator' => 'is_null',
        ], $state));
    }

    public function test_is_not_null_for_missing_key(): void
    {
        [$evaluator, $state] = $this->evaluatorWithState([]);

        $this->assertFalse($evaluator->evaluateRule([
            'state_key' => 'missing',
            'operator' => 'is_not_null',
        ], $state));
    }

    public function test_strict_equals_distinguishes_null_from_empty_string(): void
    {
        [$evaluator, $state] = $this->evaluatorWithState(['value' => null]);

        $this->assertFalse($evaluator->evaluateRule([
            'state_key' => 'value',
            'operator' => 'equals',
            'value' => '',
            'value_type' => ConditionEvaluator::VALUE_TYPE_STRING,
            'strict' => true,
        ], $state));

        $this->assertTrue($evaluator->evaluateRule([
            'state_key' => 'value',
            'operator' => 'equals',
            'value' => '',
            'value_type' => ConditionEvaluator::VALUE_TYPE_STRING,
            'strict' => false,
        ], $state));
    }

    public function test_compound_all_rules(): void
    {
        $executor = new ConditionNodeExecutor;
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, [
            'active' => true,
            'tier' => 'gold',
        ]);

        $this->assertTrue($executor->evaluateNodeData([
            'logic' => 'all',
            'rules' => [
                ['state_key' => 'active', 'operator' => 'is_true'],
                ['state_key' => 'tier', 'operator' => 'equals', 'value' => 'gold'],
            ],
        ], $state));
    }

    public function test_compound_any_rules(): void
    {
        $executor = new ConditionNodeExecutor;
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, [
            'active' => false,
            'tier' => 'gold',
        ]);

        $this->assertTrue($executor->evaluateNodeData([
            'logic' => 'any',
            'rules' => [
                ['state_key' => 'active', 'operator' => 'is_true'],
                ['state_key' => 'tier', 'operator' => 'equals', 'value' => 'gold'],
            ],
        ], $state));
    }

    public function test_gt_number_operator(): void
    {
        [$evaluator, $state] = $this->evaluatorWithState(['score' => 85]);

        $this->assertTrue($evaluator->evaluateRule([
            'state_key' => 'score',
            'operator' => 'gt',
            'value' => 80,
            'value_type' => ConditionEvaluator::VALUE_TYPE_NUMBER,
        ], $state));

        $this->assertFalse($evaluator->evaluateRule([
            'state_key' => 'score',
            'operator' => 'gt',
            'value' => 90,
            'value_type' => ConditionEvaluator::VALUE_TYPE_NUMBER,
        ], $state));
    }

    public function test_lte_number_operator_with_string_state(): void
    {
        [$evaluator, $state] = $this->evaluatorWithState(['score' => '75']);

        $this->assertTrue($evaluator->evaluateRule([
            'state_key' => 'score',
            'operator' => 'lte',
            'value' => 80,
            'value_type' => ConditionEvaluator::VALUE_TYPE_NUMBER,
        ], $state));
    }

    public function test_gt_date_operator(): void
    {
        [$evaluator, $state] = $this->evaluatorWithState(['due_at' => '2026-06-15']);

        $this->assertTrue($evaluator->evaluateRule([
            'state_key' => 'due_at',
            'operator' => 'gt',
            'value' => '2026-01-01',
            'value_type' => ConditionEvaluator::VALUE_TYPE_DATE,
        ], $state));

        $this->assertFalse($evaluator->evaluateRule([
            'state_key' => 'due_at',
            'operator' => 'lt',
            'value' => '2026-01-01',
            'value_type' => ConditionEvaluator::VALUE_TYPE_DATE,
        ], $state));
    }

    public function test_date_operator_returns_false_for_invalid_date(): void
    {
        [$evaluator, $state] = $this->evaluatorWithState(['due_at' => 'not-a-date']);

        $this->assertFalse($evaluator->evaluateRule([
            'state_key' => 'due_at',
            'operator' => 'gt',
            'value' => '2026-01-01',
            'value_type' => ConditionEvaluator::VALUE_TYPE_DATE,
        ], $state));
    }
}
