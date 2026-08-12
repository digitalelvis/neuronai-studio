<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\SwitchNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;

class SwitchNodeExecutorTest extends TestCase
{
    /** @return array{0: SwitchNodeExecutor, 1: BuilderWorkflowState} */
    protected function executorWithState(array $stateData = []): array
    {
        $context = new GraphContext([], []);

        return [
            new SwitchNodeExecutor,
            new BuilderWorkflowState($context, null, $stateData),
        ];
    }

    protected function runSwitch(array $nodeData, array $stateData = []): string
    {
        [$executor, $state] = $this->executorWithState($stateData);

        return $executor->execute(['data' => $nodeData], $state, $state->graphContext);
    }

    public function test_returns_first_matching_case(): void
    {
        $this->assertSame('gold', $this->runSwitch([
            'cases' => [
                [
                    'id' => 'gold',
                    'state_key' => 'tier',
                    'operator' => 'equals',
                    'value' => 'gold',
                ],
                [
                    'id' => 'silver',
                    'state_key' => 'tier',
                    'operator' => 'equals',
                    'value' => 'silver',
                ],
            ],
        ], ['tier' => 'gold']));
    }

    public function test_returns_default_when_no_case_matches(): void
    {
        $this->assertSame('default', $this->runSwitch([
            'cases' => [
                [
                    'id' => 'gold',
                    'state_key' => 'tier',
                    'operator' => 'equals',
                    'value' => 'gold',
                ],
            ],
        ], ['tier' => 'bronze']));
    }

    public function test_number_gt_case_matches(): void
    {
        $this->assertSame('high', $this->runSwitch([
            'cases' => [
                [
                    'id' => 'high',
                    'state_key' => 'score',
                    'operator' => 'gt',
                    'value' => 80,
                    'value_type' => 'number',
                ],
            ],
        ], ['score' => 95]));
    }

    public function test_date_lt_case_matches(): void
    {
        $this->assertSame('overdue', $this->runSwitch([
            'cases' => [
                [
                    'id' => 'overdue',
                    'state_key' => 'due_at',
                    'operator' => 'lt',
                    'value' => '2026-01-01',
                    'value_type' => 'date',
                ],
            ],
        ], ['due_at' => '2025-12-01']));
    }

    public function test_case_order_matters(): void
    {
        $this->assertSame('first', $this->runSwitch([
            'cases' => [
                [
                    'id' => 'first',
                    'state_key' => 'tier',
                    'operator' => 'not_empty',
                ],
                [
                    'id' => 'second',
                    'state_key' => 'tier',
                    'operator' => 'equals',
                    'value' => 'gold',
                ],
            ],
        ], ['tier' => 'gold']));
    }

    public function test_workflow_routes_matching_case_branch(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Switch Flow',
            'slug' => 'switch-flow',
            'graph' => $this->switchBranchGraph(),
        ]);

        $trace = app(WorkflowRunner::class)->run($workflow, [
            'message' => 'run',
            'state' => ['tier' => 'gold'],
        ]);

        $this->assertEquals('completed', $trace->status);
        $this->assertEquals('gold_branch', $trace->output['branch'] ?? null);
    }

    public function test_workflow_routes_default_branch(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Switch Default Flow',
            'slug' => 'switch-default-flow',
            'graph' => $this->switchBranchGraph(),
        ]);

        $trace = app(WorkflowRunner::class)->run($workflow, [
            'message' => 'run',
            'state' => ['tier' => 'bronze'],
        ]);

        $this->assertEquals('completed', $trace->status);
        $this->assertEquals('default_branch', $trace->output['branch'] ?? null);
    }

    /** @return array<string, mixed> */
    protected function switchBranchGraph(): array
    {
        return [
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'switch_1', 'type' => 'switch', 'position' => ['x' => 200, 'y' => 0], 'data' => [
                    'cases' => [
                        [
                            'id' => 'gold',
                            'label' => 'Gold',
                            'state_key' => 'tier',
                            'operator' => 'equals',
                            'value' => 'gold',
                        ],
                    ],
                ]],
                ['id' => 'set_gold', 'type' => 'set_state', 'position' => ['x' => 400, 'y' => -50], 'data' => [
                    'key' => 'branch',
                    'value' => 'gold_branch',
                ]],
                ['id' => 'set_default', 'type' => 'set_state', 'position' => ['x' => 400, 'y' => 50], 'data' => [
                    'key' => 'branch',
                    'value' => 'default_branch',
                ]],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 600, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'switch_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e2', 'source' => 'switch_1', 'target' => 'set_gold', 'sourceHandle' => 'gold', 'targetHandle' => 'default'],
                ['id' => 'e3', 'source' => 'switch_1', 'target' => 'set_default', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e4', 'source' => 'set_gold', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e5', 'source' => 'set_default', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
            ],
        ];
    }
}
