<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\ParallelBranchInterruptException;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\AgentNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\NodeExecutorRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\Parallel\ConcurrentBranchScheduler;
use DigitalElvis\NeuronAIStudio\Runtime\StructuredOutput\StructuredOutputResolver;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use InvalidArgumentException;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Tools\Tool;
use RuntimeException;

use function Amp\delay;

class ParallelToolApprovalTest extends TestCase
{
    /** @return array<string, mixed> */
    protected function parallelApprovalGraph(int $approvalAgentId, ?int $plainAgentId = null): array
    {
        $branchB = $plainAgentId !== null
            ? ['id' => 'branch_b_node', 'type' => 'agent', 'position' => ['x' => 200, 'y' => 50], 'data' => [
                'agent_id' => $plainAgentId,
                'output_key' => 'branch_b',
                'message' => 'plain branch',
            ]]
            : ['id' => 'branch_b_node', 'type' => 'set_state', 'position' => ['x' => 200, 'y' => 50], 'data' => [
                'key' => 'branch_b',
                'value' => 'B',
            ]];

        return [
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'fork_1', 'type' => 'fork', 'position' => ['x' => 100, 'y' => 0], 'data' => []],
                ['id' => 'branch_a_node', 'type' => 'set_state', 'position' => ['x' => 200, 'y' => -50], 'data' => [
                    'key' => 'branch_a',
                    'value' => 'A',
                ]],
                $branchB,
                ['id' => 'branch_c_node', 'type' => 'agent', 'position' => ['x' => 200, 'y' => 120], 'data' => [
                    'agent_id' => $approvalAgentId,
                    'output_key' => 'branch_c',
                    'message' => 'Delete the report',
                ]],
                ['id' => 'join_1', 'type' => 'join', 'position' => ['x' => 300, 'y' => 0], 'data' => ['output_key' => 'merged']],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 400, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'fork_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e2', 'source' => 'fork_1', 'target' => 'join_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e3', 'source' => 'fork_1', 'target' => 'branch_a_node', 'sourceHandle' => 'branch_a', 'targetHandle' => 'default'],
                ['id' => 'e4', 'source' => 'fork_1', 'target' => 'branch_b_node', 'sourceHandle' => 'branch_b', 'targetHandle' => 'default'],
                ['id' => 'e5', 'source' => 'fork_1', 'target' => 'branch_c_node', 'sourceHandle' => 'branch_c', 'targetHandle' => 'default'],
                ['id' => 'e6', 'source' => 'branch_a_node', 'target' => 'join_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e7', 'source' => 'branch_b_node', 'target' => 'join_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e8', 'source' => 'branch_c_node', 'target' => 'join_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e9', 'source' => 'join_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
            ],
            'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
        ];
    }

    /** @return array<string, mixed> */
    protected function twoBranchApprovalGraph(int $approvalAgentId): array
    {
        return [
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'fork_1', 'type' => 'fork', 'position' => ['x' => 100, 'y' => 0], 'data' => []],
                ['id' => 'branch_a_node', 'type' => 'set_state', 'position' => ['x' => 200, 'y' => -50], 'data' => [
                    'key' => 'branch_a',
                    'value' => 'A',
                ]],
                ['id' => 'branch_b_node', 'type' => 'agent', 'position' => ['x' => 200, 'y' => 50], 'data' => [
                    'agent_id' => $approvalAgentId,
                    'output_key' => 'branch_b',
                    'message' => 'Delete the report',
                ]],
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

    /** @return array<string, mixed> */
    protected function rejectableParallelGraph(int $approvalAgentId): array
    {
        return [
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'fork_1', 'type' => 'fork', 'position' => ['x' => 100, 'y' => 0], 'data' => []],
                ['id' => 'branch_a_node', 'type' => 'set_state', 'position' => ['x' => 200, 'y' => -50], 'data' => [
                    'key' => 'branch_a',
                    'value' => 'A',
                ]],
                ['id' => 'branch_b_node', 'type' => 'agent', 'position' => ['x' => 200, 'y' => 50], 'data' => [
                    'agent_id' => $approvalAgentId,
                    'output_key' => 'branch_b',
                    'message' => 'Delete the report',
                ]],
                ['id' => 'set_rejected', 'type' => 'set_state', 'position' => ['x' => 280, 'y' => 120], 'data' => [
                    'key' => 'branch_b',
                    'value' => 'rejected',
                ]],
                ['id' => 'join_1', 'type' => 'join', 'position' => ['x' => 360, 'y' => 0], 'data' => ['output_key' => 'merged']],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 460, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'fork_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e2', 'source' => 'fork_1', 'target' => 'join_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e3', 'source' => 'fork_1', 'target' => 'branch_a_node', 'sourceHandle' => 'branch_a', 'targetHandle' => 'default'],
                ['id' => 'e4', 'source' => 'fork_1', 'target' => 'branch_b_node', 'sourceHandle' => 'branch_b', 'targetHandle' => 'default'],
                ['id' => 'e5', 'source' => 'branch_a_node', 'target' => 'join_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e6', 'source' => 'branch_b_node', 'target' => 'join_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e7', 'source' => 'branch_b_node', 'target' => 'set_rejected', 'sourceHandle' => 'rejected', 'targetHandle' => 'default'],
                ['id' => 'e8', 'source' => 'set_rejected', 'target' => 'join_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e9', 'source' => 'join_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
            ],
            'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
        ];
    }

    /** @return array<string, mixed> */
    protected function mixedHumanAndApprovalGraph(int $approvalAgentId): array
    {
        return [
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'fork_1', 'type' => 'fork', 'position' => ['x' => 100, 'y' => 0], 'data' => []],
                ['id' => 'branch_a_node', 'type' => 'human', 'position' => ['x' => 200, 'y' => -50], 'data' => [
                    'prompt' => 'Approve branch A',
                    'output_key' => 'branch_a',
                ]],
                ['id' => 'branch_b_node', 'type' => 'agent', 'position' => ['x' => 200, 'y' => 50], 'data' => [
                    'agent_id' => $approvalAgentId,
                    'output_key' => 'branch_b',
                    'message' => 'Delete the report',
                ]],
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

    public function test_parallel_branch_interrupt_exception_round_trips_tool_approval_fields(): void
    {
        $exception = new ParallelBranchInterruptException(
            'fork_1',
            'join_1',
            'branch_b',
            'agent_1',
            '',
            'Approve tool',
            ParallelBranchInterruptException::REASON_TOOL_APPROVAL,
            ['input' => 'x'],
            ['branch_a' => 'A'],
            ['branch_a' => 'A'],
            [['name' => 'delete_file', 'arguments' => ['path' => '/tmp'], 'call_id' => 'c1']],
            'O:serialized',
        );

        $this->assertTrue($exception->isToolApproval());
        $this->assertSame('delete_file', $exception->pendingTools[0]['name']);
        $this->assertSame('O:serialized', $exception->serializedInterrupt);
        $this->assertSame(['branch_a' => 'A'], $exception->completedResults);
    }

    /**
     * @dataProvider concurrencyModes
     */
    public function test_fork_pauses_for_tool_approval_and_preserves_completed_branch(string $mode): void
    {
        config(['neuronai-studio.parallel.concurrency' => $mode]);

        $agent = $this->approvalAgent();
        $this->bindAgentRunner($this->toolCall());

        $events = [];
        $paused = app(WorkflowRunner::class)->run(
            WorkflowDefinition::create([
                'name' => 'Parallel Approval',
                'slug' => 'parallel-approval-'.$mode,
                'graph' => $this->twoBranchApprovalGraph($agent->id),
            ]),
            ['input' => 'go'],
            function (string $event, array $data) use (&$events) {
                $events[] = [$event, $data];
            },
        );

        $this->assertSame('awaiting_tool_approval', $paused->status);
        $this->assertSame('branch_b_node', $paused->awaiting_node_id);
        $this->assertSame('parallel', $paused->checkpoint['kind'] ?? null);
        $this->assertSame('tool_approval', $paused->checkpoint['parallel']['reason'] ?? null);
        $this->assertArrayHasKey('branch_a', $paused->checkpoint['parallel']['completed'] ?? []);
        $this->assertSame('delete_file', $paused->checkpoint['pending_tools'][0]['name'] ?? null);

        $emitted = array_column($events, 0);
        $this->assertContains('parallel_interrupt', $emitted);
        $this->assertContains('tool_approval_required', $emitted);
        $this->assertContains('branch_completed', $emitted);

        $approvalEvent = collect($events)->firstWhere(fn ($row) => $row[0] === 'tool_approval_required');
        $this->assertSame('branch_b', $approvalEvent[1]['branch_id'] ?? null);
        $this->assertSame('delete_file', $approvalEvent[1]['pending_tools'][0]['name'] ?? null);
    }

    /**
     * @dataProvider concurrencyModes
     */
    public function test_approve_resume_completes_pending_branch_and_join(string $mode): void
    {
        config(['neuronai-studio.parallel.concurrency' => $mode]);

        $agent = $this->approvalAgent();
        $this->bindAgentRunner($this->toolCall(), new AssistantMessage('Report deleted successfully.'));

        $runner = app(WorkflowRunner::class);
        $paused = $runner->run(
            WorkflowDefinition::create([
                'name' => 'Parallel Approve',
                'slug' => 'parallel-approve-'.$mode,
                'graph' => $this->twoBranchApprovalGraph($agent->id),
            ]),
            ['input' => 'go'],
        );

        $this->assertSame('awaiting_tool_approval', $paused->status);

        $events = [];
        $completed = $runner->resume(
            $paused,
            'branch_b_node',
            '',
            function (string $event, array $data) use (&$events) {
                $events[$event] = $data;
            },
            [],
            'approve',
        );

        $this->assertSame('completed', $completed->status);
        $this->assertSame(['branch_a' => 'A', 'branch_b' => 'Report deleted successfully.'], $completed->output['merged'] ?? null);
        $this->assertArrayHasKey('tool_approval_resolved', $events);
        $this->assertTrue($events['tool_approval_resolved']['approved'] ?? null);
        $this->assertSame('branch_b', $events['tool_approval_resolved']['branch_id'] ?? null);
    }

    /**
     * @dataProvider concurrencyModes
     */
    public function test_reject_resume_routes_rejected_handle_and_keeps_sibling(string $mode): void
    {
        config(['neuronai-studio.parallel.concurrency' => $mode]);

        $agent = $this->approvalAgent();
        $this->bindAgentRunner($this->toolCall(), new AssistantMessage('Understood.'));

        $runner = app(WorkflowRunner::class);
        $paused = $runner->run(
            WorkflowDefinition::create([
                'name' => 'Parallel Reject',
                'slug' => 'parallel-reject-'.$mode,
                'graph' => $this->rejectableParallelGraph($agent->id),
            ]),
            ['input' => 'go'],
        );

        $completed = $runner->resume($paused, 'branch_b_node', 'Do not delete', null, [], 'reject');

        $this->assertSame('completed', $completed->status);
        $this->assertSame(['branch_a' => 'A', 'branch_b' => 'rejected'], $completed->output['merged'] ?? null);
    }

    public function test_human_shaped_resume_against_tool_approval_pause_is_rejected(): void
    {
        config(['neuronai-studio.parallel.concurrency' => 'sequential']);

        $agent = $this->approvalAgent();
        $this->bindAgentRunner($this->toolCall());

        $runner = app(WorkflowRunner::class);
        $paused = $runner->run(
            WorkflowDefinition::create([
                'name' => 'Parallel Bad Resume',
                'slug' => 'parallel-bad-resume',
                'graph' => $this->twoBranchApprovalGraph($agent->id),
            ]),
            ['input' => 'go'],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('awaiting tool approval');

        $runner->resume($paused, 'branch_b_node', 'please continue');
    }

    public function test_missing_serialized_interrupt_fails_explicitly(): void
    {
        config(['neuronai-studio.parallel.concurrency' => 'sequential']);

        $agent = $this->approvalAgent();
        $this->bindAgentRunner($this->toolCall());

        $runner = app(WorkflowRunner::class);
        $paused = $runner->run(
            WorkflowDefinition::create([
                'name' => 'Parallel Missing Interrupt',
                'slug' => 'parallel-missing-interrupt',
                'graph' => $this->twoBranchApprovalGraph($agent->id),
            ]),
            ['input' => 'go'],
        );

        $checkpoint = $paused->checkpoint_state;
        $checkpoint['interrupt'] = null;
        $checkpoint['parallel']['interrupt'] = null;
        $paused->update(['checkpoint_state' => $checkpoint]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('serialized interrupt is missing');

        $runner->resume($paused->fresh(), 'branch_b_node', '', null, [], 'approve');
    }

    /**
     * @dataProvider concurrencyModes
     */
    public function test_multi_approval_fork_pauses_one_at_a_time(string $mode): void
    {
        config(['neuronai-studio.parallel.concurrency' => $mode]);

        $agentB = $this->approvalAgent('approval-b-'.$mode);
        $agentC = $this->approvalAgent('approval-c-'.$mode);

        // Concurrent: both approval branches may consume a tool-call on the first tick.
        // Sequential: only one branch interrupts before the other starts.
        if ($mode === 'concurrent') {
            $this->bindAgentRunner(
                $this->toolCall(),
                $this->toolCall(),
                new AssistantMessage('B done'),
                $this->toolCall(),
                new AssistantMessage('C done'),
            );
        } else {
            $this->bindAgentRunner(
                $this->toolCall(),
                new AssistantMessage('B done'),
                $this->toolCall(),
                new AssistantMessage('C done'),
            );
        }

        $runner = app(WorkflowRunner::class);
        $workflow = WorkflowDefinition::create([
            'name' => 'Multi Approval',
            'slug' => 'multi-approval-'.$mode,
            'graph' => $this->parallelApprovalGraph($agentC->id, $agentB->id),
        ]);

        $paused1 = $runner->run($workflow, ['input' => 'go']);
        $this->assertSame('awaiting_tool_approval', $paused1->status);
        $this->assertArrayHasKey('branch_a', $paused1->checkpoint['parallel']['completed'] ?? []);

        $firstPending = (string) $paused1->awaiting_node_id;
        $this->assertContains($firstPending, ['branch_b_node', 'branch_c_node']);

        $paused2 = $runner->resume($paused1, $firstPending, '', null, [], 'approve');
        $this->assertSame('awaiting_tool_approval', $paused2->status);
        $this->assertNotSame($firstPending, $paused2->awaiting_node_id);

        $completed = $runner->resume($paused2, (string) $paused2->awaiting_node_id, '', null, [], 'approve');
        $this->assertSame('completed', $completed->status);

        $merged = $completed->output['merged'] ?? [];
        $this->assertSame('A', $merged['branch_a'] ?? null);
        $this->assertContains($merged['branch_b'] ?? null, ['B done', 'C done']);
        $this->assertContains($merged['branch_c'] ?? null, ['B done', 'C done']);
        $this->assertNotSame($merged['branch_b'] ?? null, $merged['branch_c'] ?? null);
    }

    public function test_mixed_human_and_tool_approval_fork(): void
    {
        config(['neuronai-studio.parallel.concurrency' => 'sequential']);

        $agent = $this->approvalAgent('mixed-seq');
        $this->bindAgentRunner($this->toolCall(), new AssistantMessage('Tool done'));

        $runner = app(WorkflowRunner::class);
        $workflow = WorkflowDefinition::create([
            'name' => 'Mixed Parallel',
            'slug' => 'mixed-parallel-seq',
            'graph' => $this->mixedHumanAndApprovalGraph($agent->id),
        ]);

        // Sequential: branch_a (human) interrupts before branch_b starts.
        $first = $runner->run($workflow, ['input' => 'go']);
        $this->assertSame('awaiting_input', $first->status);
        $this->assertSame('branch_a_node', $first->awaiting_node_id);

        $second = $runner->resume($first, (string) $first->awaiting_node_id, 'human-ok');
        $this->assertSame('awaiting_tool_approval', $second->status);
        $this->assertSame('branch_b_node', $second->awaiting_node_id);
        $this->assertArrayHasKey('branch_a', $second->checkpoint['parallel']['completed'] ?? []);

        $completed = $runner->resume($second, (string) $second->awaiting_node_id, '', null, [], 'approve');
        $this->assertSame('completed', $completed->status);
        $this->assertSame(['branch_a' => 'human-ok', 'branch_b' => 'Tool done'], $completed->output['merged'] ?? null);
    }

    public function test_concurrent_scheduler_surfaces_lowest_branch_order_interrupt(): void
    {
        config(['neuronai-studio.parallel.concurrency' => 'concurrent']);

        $scheduler = new ConcurrentBranchScheduler;
        $this->assertTrue($scheduler->shouldRunConcurrent(2));

        try {
            $scheduler->run([
                'branch_a' => static function (): array {
                    delay(0.02);
                    throw new ParallelBranchInterruptException(
                        'fork_1',
                        'join_1',
                        'branch_a',
                        'node_a',
                        'out',
                        'human A',
                        ParallelBranchInterruptException::REASON_HUMAN,
                        [],
                        [],
                        [],
                    );
                },
                'branch_b' => static function (): array {
                    throw new ParallelBranchInterruptException(
                        'fork_1',
                        'join_1',
                        'branch_b',
                        'node_b',
                        '',
                        'tool B',
                        ParallelBranchInterruptException::REASON_TOOL_APPROVAL,
                        [],
                        [],
                        [],
                        [['name' => 't', 'arguments' => [], 'call_id' => null]],
                        'interrupt',
                    );
                },
            ]);
            $this->fail('Expected ParallelBranchInterruptException');
        } catch (ParallelBranchInterruptException $exception) {
            $this->assertSame('branch_a', $exception->branchId);
            $this->assertSame(ParallelBranchInterruptException::REASON_HUMAN, $exception->reason);
        }
    }

    public function test_sequential_scheduler_preserves_completed_results_on_later_interrupt(): void
    {
        config(['neuronai-studio.parallel.concurrency' => 'sequential']);

        $scheduler = new ConcurrentBranchScheduler;

        try {
            $scheduler->run([
                'branch_a' => static fn (): array => [['branch_a' => 'A'], ['branch_a' => 'A']],
                'branch_b' => static function (): array {
                    throw new ParallelBranchInterruptException(
                        'fork_1',
                        'join_1',
                        'branch_b',
                        'node_b',
                        '',
                        'tool B',
                        ParallelBranchInterruptException::REASON_TOOL_APPROVAL,
                        ['x' => 1],
                        [],
                        [],
                        [['name' => 't', 'arguments' => [], 'call_id' => null]],
                        'interrupt',
                    );
                },
            ]);
            $this->fail('Expected ParallelBranchInterruptException');
        } catch (ParallelBranchInterruptException $exception) {
            $this->assertSame('branch_b', $exception->branchId);
            $this->assertSame(['branch_a' => 'A'], $exception->completedResults);
        }
    }

    /** @return array<string, array{0: string}> */
    public static function concurrencyModes(): array
    {
        return [
            'sequential' => ['sequential'],
            'concurrent' => ['concurrent'],
        ];
    }

    protected function approvalAgent(string $slug = 'approval-agent'): AgentDefinition
    {
        return AgentDefinition::create([
            'name' => 'Approval Agent',
            'slug' => $slug,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You act on tools.',
            'require_tool_approval' => true,
        ]);
    }

    protected function toolCall(): ToolCallMessage
    {
        $tool = Tool::make('delete_file', 'Deletes a file')
            ->setCallable(new ParallelApprovableToolHandler)
            ->setInputs(['path' => '/tmp/report.txt'])
            ->setCallId('call_1');

        return new ToolCallMessage(null, [$tool]);
    }

    protected function bindAgentRunner(Message ...$responses): void
    {
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn(new FakeAIProvider(...$responses));

        $toolResolver = $this->createMock(ToolResolver::class);
        $toolResolver->method('resolveMany')->willReturn([]);

        $runner = new AgentRunner(
            $registry,
            $toolResolver,
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );

        $this->app->instance(AgentRunner::class, $runner);
        $this->app->make(NodeExecutorRegistry::class)->register(
            'agent',
            new AgentNodeExecutor($runner, new MessageFactory, app(StructuredOutputResolver::class)),
        );
    }
}

class ParallelApprovableToolHandler
{
    public function __invoke(): string
    {
        return 'file deleted';
    }
}
