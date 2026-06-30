<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\ConditionNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;

class ConditionNodeExecutorTest extends TestCase
{
    /** @return array{0: ConditionNodeExecutor, 1: BuilderWorkflowState} */
    protected function executorWithState(array $stateData = []): array
    {
        $context = new GraphContext([], []);

        return [
            new ConditionNodeExecutor,
            new BuilderWorkflowState($context, null, $stateData),
        ];
    }

    protected function runCondition(array $nodeData, array $stateData = []): string
    {
        [$executor, $state] = $this->executorWithState($stateData);

        return $executor->execute(['data' => $nodeData], $state, $state->graphContext);
    }

    public function test_defaults_to_input_key_and_not_empty_operator(): void
    {
        $this->assertSame('true', $this->runCondition([], ['input' => 'hello']));
        $this->assertSame('false', $this->runCondition([], ['input' => '']));
    }

    public function test_not_empty_operator(): void
    {
        $this->assertSame('true', $this->runCondition(
            ['state_key' => 'tier', 'operator' => 'not_empty'],
            ['tier' => 'gold'],
        ));

        $this->assertSame('false', $this->runCondition(
            ['state_key' => 'tier', 'operator' => 'not_empty'],
            ['tier' => ''],
        ));
    }

    public function test_empty_operator(): void
    {
        $this->assertSame('true', $this->runCondition(
            ['state_key' => 'notes', 'operator' => 'empty'],
            ['notes' => ''],
        ));

        $this->assertSame('false', $this->runCondition(
            ['state_key' => 'notes', 'operator' => 'empty'],
            ['notes' => 'present'],
        ));
    }

    public function test_equals_operator(): void
    {
        $this->assertSame('true', $this->runCondition(
            ['state_key' => 'tier', 'operator' => 'equals', 'value' => 'gold'],
            ['tier' => 'gold'],
        ));

        $this->assertSame('false', $this->runCondition(
            ['state_key' => 'tier', 'operator' => 'equals', 'value' => 'gold'],
            ['tier' => 'silver'],
        ));
    }

    public function test_not_equals_operator(): void
    {
        $this->assertSame('true', $this->runCondition(
            ['state_key' => 'tier', 'operator' => 'not_equals', 'value' => 'gold'],
            ['tier' => 'silver'],
        ));

        $this->assertSame('false', $this->runCondition(
            ['state_key' => 'tier', 'operator' => 'not_equals', 'value' => 'gold'],
            ['tier' => 'gold'],
        ));
    }

    public function test_contains_operator(): void
    {
        $this->assertSame('true', $this->runCondition(
            ['state_key' => 'input', 'operator' => 'contains', 'value' => 'error'],
            ['input' => 'An error occurred'],
        ));

        $this->assertSame('false', $this->runCondition(
            ['state_key' => 'input', 'operator' => 'contains', 'value' => 'error'],
            ['input' => 'All good'],
        ));

        $this->assertSame('false', $this->runCondition(
            ['state_key' => 'count', 'operator' => 'contains', 'value' => '1'],
            ['count' => 10],
        ));
    }

    public function test_workflow_routes_true_branch_when_condition_matches(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Condition True Flow',
            'slug' => 'condition-true-flow',
            'graph' => $this->conditionBranchGraph(),
        ]);

        $trace = app(WorkflowRunner::class)->run($workflow, [
            'message' => 'run',
            'state' => ['tier' => 'gold'],
        ]);

        $this->assertEquals('completed', $trace->status);
        $this->assertEquals('true_branch', $trace->output['branch'] ?? null);
    }

    public function test_workflow_routes_false_branch_when_condition_fails(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Condition False Flow',
            'slug' => 'condition-false-flow',
            'graph' => $this->conditionBranchGraph(),
        ]);

        $trace = app(WorkflowRunner::class)->run($workflow, [
            'message' => 'run',
            'state' => ['tier' => 'silver'],
        ]);

        $this->assertEquals('completed', $trace->status);
        $this->assertEquals('false_branch', $trace->output['branch'] ?? null);
    }

    /** @return array<string, mixed> */
    protected function conditionBranchGraph(): array
    {
        return [
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'cond_1', 'type' => 'condition', 'position' => ['x' => 200, 'y' => 0], 'data' => [
                    'state_key' => 'tier',
                    'operator' => 'equals',
                    'value' => 'gold',
                ]],
                ['id' => 'set_true', 'type' => 'set_state', 'position' => ['x' => 400, 'y' => -50], 'data' => [
                    'key' => 'branch',
                    'value' => 'true_branch',
                ]],
                ['id' => 'set_false', 'type' => 'set_state', 'position' => ['x' => 400, 'y' => 50], 'data' => [
                    'key' => 'branch',
                    'value' => 'false_branch',
                ]],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 600, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'cond_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e2', 'source' => 'cond_1', 'target' => 'set_true', 'sourceHandle' => 'true', 'targetHandle' => 'default'],
                ['id' => 'e3', 'source' => 'cond_1', 'target' => 'set_false', 'sourceHandle' => 'false', 'targetHandle' => 'default'],
                ['id' => 'e4', 'source' => 'set_true', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e5', 'source' => 'set_false', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
            ],
        ];
    }
}
