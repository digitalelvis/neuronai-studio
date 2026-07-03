<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\GraphValidator;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;

class WorkflowParallelExecutionTest extends TestCase
{
    /** @return array<string, mixed> */
    protected function parallelGraph(string $branchAType = 'set_state', array $branchAData = ['key' => 'branch_a', 'value' => 'A']): array
    {
        return [
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'fork_1', 'type' => 'fork', 'position' => ['x' => 100, 'y' => 0], 'data' => []],
                ['id' => 'branch_a_node', 'type' => $branchAType, 'position' => ['x' => 200, 'y' => -50], 'data' => $branchAData],
                ['id' => 'branch_b_node', 'type' => 'set_state', 'position' => ['x' => 200, 'y' => 50], 'data' => ['key' => 'branch_b', 'value' => 'B']],
                ['id' => 'join_1', 'type' => 'join', 'position' => ['x' => 300, 'y' => 0], 'data' => ['output_key' => 'merged']],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 400, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'fork_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e2', 'source' => 'fork_1', 'target' => 'join_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e3', 'source' => 'fork_1', 'target' => 'branch_a_node', 'sourceHandle' => 'branch_a', 'targetHandle' => 'default'],
                ['id' => 'e4', 'source' => 'fork_1', 'target' => 'branch_b_node', 'sourceHandle' => 'branch_b', 'targetHandle' => 'default'],
                ['id' => 'e5', 'source' => 'branch_a_node', 'target' => 'join_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e6', 'source' => 'branch_b_node', 'target' => 'join_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e7', 'source' => 'join_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
            ],
            'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
        ];
    }

    public function test_fork_runs_two_branches_and_join_merges_results(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Parallel Flow',
            'slug' => 'parallel-flow',
            'graph' => $this->parallelGraph(),
        ]);

        $events = [];
        $trace = app(WorkflowRunner::class)->run($workflow, ['input' => 'go'], function (string $event, array $data) use (&$events) {
            $events[] = [$event, $data];
        });

        $this->assertSame('completed', $trace->status);
        $this->assertSame(['branch_a' => 'A', 'branch_b' => 'B'], $trace->output['merged'] ?? null);

        $emitted = array_column($events, 0);
        $this->assertContains('branch_started', $emitted);
        $this->assertContains('branch_completed', $emitted);
    }

    public function test_human_branch_interrupt_pauses_and_resume_completes_pending_branch(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Parallel HITL Flow',
            'slug' => 'parallel-hitl-flow',
            'graph' => $this->parallelGraph('human', [
                'prompt' => 'Approve branch A',
                'output_key' => 'branch_a',
            ]),
        ]);

        $runner = app(WorkflowRunner::class);
        $events = [];

        $paused = $runner->run($workflow, ['input' => 'go'], function (string $event, array $data) use (&$events) {
            $events[] = [$event, $data];
        });

        $this->assertSame('awaiting_input', $paused->status);
        $this->assertSame('branch_a_node', $paused->awaiting_node_id);
        $emitted = array_column($events, 0);
        $this->assertContains('parallel_interrupt', $emitted);
        $this->assertContains('human_input_required', $emitted);

        $completed = $runner->resume($paused, 'branch_a_node', 'approved');

        $this->assertSame('completed', $completed->status);
        $this->assertSame(['branch_a' => 'approved', 'branch_b' => 'B'], $completed->output['merged'] ?? null);
    }

    public function test_validator_rejects_fork_without_join(): void
    {
        $validator = app(GraphValidator::class);

        $result = $validator->validate([
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'fork_1', 'type' => 'fork', 'position' => ['x' => 100, 'y' => 0], 'data' => []],
                ['id' => 'branch_a_node', 'type' => 'set_state', 'position' => ['x' => 200, 'y' => 0], 'data' => ['key' => 'branch_a', 'value' => 'A']],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 300, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'fork_1', 'sourceHandle' => 'default'],
                ['id' => 'e2', 'source' => 'fork_1', 'target' => 'branch_a_node', 'sourceHandle' => 'branch_a'],
                ['id' => 'e3', 'source' => 'branch_a_node', 'target' => 'stop_1', 'sourceHandle' => 'default'],
            ],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('join', strtolower(implode(' ', $result['errors'])));
    }
}
