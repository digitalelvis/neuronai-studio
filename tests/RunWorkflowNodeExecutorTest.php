<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\RunWorkflowNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Support\Str;
use RuntimeException;

class RunWorkflowNodeExecutorTest extends TestCase
{
    protected function childGraphWithGreeting(): array
    {
        return [
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'set_1', 'type' => 'set_state', 'position' => ['x' => 200, 'y' => 0], 'data' => ['key' => 'greeting', 'value' => 'Hello child']],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 400, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'set_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e2', 'source' => 'set_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
            ],
        ];
    }

    public function test_step_mode_runs_child_and_writes_output_key(): void
    {
        $child = WorkflowDefinition::create([
            'name' => 'Child Flow',
            'slug' => 'child-flow-executor',
            'graph' => $this->childGraphWithGreeting(),
        ]);

        $parentThread = StudioThread::create([
            'id' => (string) Str::uuid(),
            'entity_type' => WorkflowDefinition::class,
            'entity_id' => 999,
        ]);
        $parentRun = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $parentThread->id,
            'status' => 'running',
        ]);

        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, [
            'input' => 'parent-msg',
            'lead_id' => '42',
            '__studio_run_id' => $parentRun->id,
        ]);

        $handle = app(RunWorkflowNodeExecutor::class)->execute([
            'id' => 'run_wf_1',
            'data' => [
                'workflow_id' => (string) $child->id,
                'message' => '{{input}}',
                'state_map' => [
                    ['key' => 'lead_id', 'value' => '{{lead_id}}'],
                ],
                'output_key' => 'child_output',
            ],
        ], $state, $context);

        $this->assertSame('default', $handle);

        $written = $state->get('child_output');
        $this->assertIsString($written);
        $decoded = json_decode($written, true);
        $this->assertIsArray($decoded);
        $this->assertSame('Hello child', $decoded['greeting'] ?? null);
        $this->assertSame('parent-msg', $decoded['input'] ?? null);
        $this->assertSame('42', $decoded['lead_id'] ?? null);

        $nested = StudioRun::query()->where('parent_run_id', $parentRun->id)->first();
        $this->assertNotNull($nested);
        $this->assertSame('completed', $nested->status);
        $this->assertSame(1, $nested->output[WorkflowRunner::NESTING_DEPTH_INPUT_KEY] ?? null);
    }

    public function test_rejects_nesting_depth_above_max(): void
    {
        $child = WorkflowDefinition::create([
            'name' => 'Deep Child',
            'slug' => 'deep-child-executor',
            'graph' => $this->childGraphWithGreeting(),
        ]);

        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, [
            'input' => 'x',
            WorkflowRunner::NESTING_DEPTH_INPUT_KEY => RunWorkflowNodeExecutor::MAX_NESTING_DEPTH,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('nesting depth');

        app(RunWorkflowNodeExecutor::class)->execute([
            'data' => [
                'workflow_id' => (string) $child->id,
                'message' => 'hi',
            ],
        ], $state, $context);
    }

    public function test_rejects_child_hitl_interrupt(): void
    {
        $child = WorkflowDefinition::create([
            'name' => 'Hitl Child',
            'slug' => 'hitl-child-executor',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $paused = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => StudioThread::create(['id' => (string) Str::uuid()])->id,
            'status' => 'awaiting_input',
            'output' => null,
        ]);

        $runner = $this->createMock(WorkflowRunner::class);
        $runner->expects($this->once())
            ->method('run')
            ->willReturn($paused);

        $executor = new RunWorkflowNodeExecutor($runner);
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, ['input' => 'x']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('human interrupt');

        $executor->execute([
            'data' => [
                'workflow_id' => (string) $child->id,
                'message' => 'hi',
            ],
        ], $state, $context);
    }

    public function test_rejects_missing_workflow_id(): void
    {
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('workflow_id');

        app(RunWorkflowNodeExecutor::class)->execute(['data' => []], $state, $context);
    }

    public function test_rejects_missing_target_workflow(): void
    {
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not found');

        app(RunWorkflowNodeExecutor::class)->execute([
            'data' => ['workflow_id' => '999999'],
        ], $state, $context);
    }

    public function test_serializes_string_child_output_as_is(): void
    {
        $child = WorkflowDefinition::create([
            'name' => 'String Out Child',
            'slug' => 'string-out-child',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $completed = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => StudioThread::create(['id' => (string) Str::uuid()])->id,
            'status' => 'completed',
            'output' => 'plain-result',
        ]);

        $runner = $this->createMock(WorkflowRunner::class);
        $runner->method('run')->willReturn($completed);

        $executor = new RunWorkflowNodeExecutor($runner);
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, []);

        $executor->execute([
            'data' => [
                'workflow_id' => (string) $child->id,
                'message' => 'hi',
                'output_key' => 'out',
            ],
        ], $state, $context);

        $this->assertSame('plain-result', $state->get('out'));
    }
}
