<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\MaxLoopIterationsException;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\WorkflowExecutionException;
use DigitalElvis\NeuronAIStudio\Runtime\GraphValidator;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use DigitalElvis\NeuronAIStudio\Services\TemplateInstaller;

class WorkflowLoopTest extends TestCase
{
    public function test_rejects_cyclic_graph_without_loop_node(): void
    {
        $validator = app(GraphValidator::class);
        $result = $validator->validate([
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'data' => []],
                ['id' => 'set_1', 'type' => 'set_state', 'data' => []],
                ['id' => 'stop_1', 'type' => 'stop', 'data' => []],
            ],
            'edges' => [
                ['source' => 'start_1', 'target' => 'set_1', 'sourceHandle' => 'default'],
                ['source' => 'set_1', 'target' => 'set_1', 'sourceHandle' => 'default'],
                ['source' => 'set_1', 'target' => 'stop_1', 'sourceHandle' => 'default'],
            ],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('loop node', strtolower(implode(' ', $result['errors'])));
    }

    public function test_accepts_cyclic_graph_with_authorized_loop(): void
    {
        $validator = app(GraphValidator::class);
        $result = $validator->validate($this->loopExitGraph());

        $this->assertTrue($result['valid'], implode(' ', $result['errors']));
    }

    public function test_workflow_exits_loop_when_condition_met(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Loop Exit Flow',
            'slug' => 'loop-exit-flow',
            'graph' => $this->loopExitGraph(),
        ]);

        $trace = app(WorkflowRunner::class)->run($workflow, [
            'message' => 'run',
            'state' => ['ready' => 'yes'],
        ]);

        $this->assertEquals('completed', $trace->status);
        $this->assertEquals('exited', $trace->output['result'] ?? null);
        $this->assertSame(1, $trace->output['__loop_iterations']['loop_1'] ?? null);
    }

    public function test_workflow_loops_until_condition_then_exits(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Loop Continue Flow',
            'slug' => 'loop-continue-flow',
            'graph' => $this->loopContinueThenExitGraph(),
        ]);

        $trace = app(WorkflowRunner::class)->run($workflow, ['message' => 'run']);

        $this->assertEquals('completed', $trace->status);
        $this->assertEquals('done', $trace->output['result'] ?? null);
        $this->assertSame(2, $trace->output['__loop_iterations']['loop_1'] ?? null);
    }

    public function test_workflow_fails_when_max_steps_exceeded(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Loop Max Steps Flow',
            'slug' => 'loop-max-steps-flow',
            'graph' => $this->loopNeverExitsGraph(2),
        ]);

        try {
            app(WorkflowRunner::class)->run($workflow, ['message' => 'run']);
            $this->fail('Expected MaxLoopIterationsException was not thrown.');
        } catch (WorkflowExecutionException $exception) {
            $this->assertInstanceOf(MaxLoopIterationsException::class, $exception->getPrevious());
            $this->assertStringContainsString('Max loop iterations exceeded', $exception->getMessage());
        }

        $trace = $workflow->traces()->latest('id')->first();
        $this->assertNotNull($trace);
        $this->assertEquals('failed', $trace->status);
        $this->assertStringContainsString('Max loop iterations exceeded', (string) $trace->error_message);
        $this->assertGreaterThan(0, $trace->steps()->count(), 'Failed traces should persist partial step history.');
    }

    public function test_resume_after_human_inside_loop_body(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Loop HITL Flow',
            'slug' => 'loop-hitl-flow',
            'graph' => $this->loopWithHumanGraph(),
        ]);

        $runner = app(WorkflowRunner::class);
        $trace = $runner->run($workflow, ['message' => 'run']);

        $this->assertEquals('awaiting_input', $trace->status);
        $this->assertEquals('human_1', $trace->awaiting_node_id);

        $resumed = $runner->resume($trace, 'human_1', 'user@example.com');

        $this->assertEquals('completed', $resumed->status);
        $this->assertEquals('user@example.com', $resumed->output['human_response'] ?? null);
        $this->assertEquals('done', $resumed->output['result'] ?? null);
    }

    public function test_lead_qualification_loop_template_is_valid(): void
    {
        $workflow = app(TemplateInstaller::class)->installWorkflow('lead-qualification-loop');
        $result = app(GraphValidator::class)->validate($workflow->graph);

        $this->assertTrue($result['valid'], implode(' ', $result['errors']));
        $this->assertTrue(
            collect($workflow->graph['nodes'] ?? [])->contains(fn (array $n) => ($n['type'] ?? '') === 'loop'),
        );
    }

    /** @return array<string, mixed> */
    protected function loopExitGraph(): array
    {
        return [
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'loop_1', 'type' => 'loop', 'position' => ['x' => 200, 'y' => 0], 'data' => [
                    'max_steps' => 5,
                    'state_key' => 'ready',
                    'operator' => 'equals',
                    'value' => 'yes',
                ]],
                ['id' => 'set_exit', 'type' => 'set_state', 'position' => ['x' => 400, 'y' => 0], 'data' => [
                    'key' => 'result',
                    'value' => 'exited',
                ]],
                ['id' => 'set_continue', 'type' => 'set_state', 'position' => ['x' => 400, 'y' => 100], 'data' => [
                    'key' => 'result',
                    'value' => 'continued',
                ]],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 600, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'loop_1', 'sourceHandle' => 'default'],
                ['id' => 'e2', 'source' => 'loop_1', 'target' => 'set_exit', 'sourceHandle' => 'exit'],
                ['id' => 'e3', 'source' => 'loop_1', 'target' => 'set_continue', 'sourceHandle' => 'continue'],
                ['id' => 'e4', 'source' => 'set_continue', 'target' => 'loop_1', 'sourceHandle' => 'default'],
                ['id' => 'e5', 'source' => 'set_exit', 'target' => 'stop_1', 'sourceHandle' => 'default'],
                ['id' => 'e6', 'source' => 'set_continue', 'target' => 'stop_1', 'sourceHandle' => 'default'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function loopContinueThenExitGraph(): array
    {
        return [
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'loop_1', 'type' => 'loop', 'position' => ['x' => 200, 'y' => 0], 'data' => [
                    'max_steps' => 5,
                    'state_key' => 'ready',
                    'operator' => 'equals',
                    'value' => 'yes',
                ]],
                ['id' => 'set_ready', 'type' => 'set_state', 'position' => ['x' => 400, 'y' => 100], 'data' => [
                    'key' => 'ready',
                    'value' => 'yes',
                ]],
                ['id' => 'set_done', 'type' => 'set_state', 'position' => ['x' => 400, 'y' => 0], 'data' => [
                    'key' => 'result',
                    'value' => 'done',
                ]],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 600, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'loop_1', 'sourceHandle' => 'default'],
                ['id' => 'e2', 'source' => 'loop_1', 'target' => 'set_ready', 'sourceHandle' => 'continue'],
                ['id' => 'e3', 'source' => 'set_ready', 'target' => 'loop_1', 'sourceHandle' => 'default'],
                ['id' => 'e4', 'source' => 'loop_1', 'target' => 'set_done', 'sourceHandle' => 'exit'],
                ['id' => 'e5', 'source' => 'set_done', 'target' => 'stop_1', 'sourceHandle' => 'default'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function loopNeverExitsGraph(int $maxSteps): array
    {
        return [
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'loop_1', 'type' => 'loop', 'position' => ['x' => 200, 'y' => 0], 'data' => [
                    'max_steps' => $maxSteps,
                    'state_key' => 'ready',
                    'operator' => 'equals',
                    'value' => 'yes',
                ]],
                ['id' => 'set_tick', 'type' => 'set_state', 'position' => ['x' => 400, 'y' => 100], 'data' => [
                    'key' => 'tick',
                    'value' => '1',
                ]],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 600, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'loop_1', 'sourceHandle' => 'default'],
                ['id' => 'e2', 'source' => 'loop_1', 'target' => 'set_tick', 'sourceHandle' => 'continue'],
                ['id' => 'e3', 'source' => 'set_tick', 'target' => 'loop_1', 'sourceHandle' => 'default'],
                ['id' => 'e4', 'source' => 'loop_1', 'target' => 'stop_1', 'sourceHandle' => 'exit'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function loopWithHumanGraph(): array
    {
        return [
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'loop_1', 'type' => 'loop', 'position' => ['x' => 200, 'y' => 0], 'data' => [
                    'max_steps' => 5,
                    'state_key' => 'ready',
                    'operator' => 'equals',
                    'value' => 'yes',
                ]],
                ['id' => 'human_1', 'type' => 'human', 'position' => ['x' => 400, 'y' => 100], 'data' => [
                    'prompt' => 'Enter email',
                    'output_key' => 'human_response',
                ]],
                ['id' => 'set_ready', 'type' => 'set_state', 'position' => ['x' => 600, 'y' => 100], 'data' => [
                    'key' => 'ready',
                    'value' => 'yes',
                ]],
                ['id' => 'set_done', 'type' => 'set_state', 'position' => ['x' => 400, 'y' => 0], 'data' => [
                    'key' => 'result',
                    'value' => 'done',
                ]],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 600, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'loop_1', 'sourceHandle' => 'default'],
                ['id' => 'e2', 'source' => 'loop_1', 'target' => 'human_1', 'sourceHandle' => 'continue'],
                ['id' => 'e3', 'source' => 'human_1', 'target' => 'set_ready', 'sourceHandle' => 'default'],
                ['id' => 'e4', 'source' => 'set_ready', 'target' => 'loop_1', 'sourceHandle' => 'default'],
                ['id' => 'e5', 'source' => 'loop_1', 'target' => 'set_done', 'sourceHandle' => 'exit'],
                ['id' => 'e6', 'source' => 'set_done', 'target' => 'stop_1', 'sourceHandle' => 'default'],
            ],
        ];
    }
}
