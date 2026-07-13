<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Support\Str;

class WorkflowRunnerTest extends TestCase
{
    public function test_runs_simple_workflow(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Set State Flow',
            'slug' => 'set-state-flow',
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    ['id' => 'set_1', 'type' => 'set_state', 'position' => ['x' => 200, 'y' => 0], 'data' => ['key' => 'greeting', 'value' => 'Hello']],
                    ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 400, 'y' => 0], 'data' => []],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start_1', 'target' => 'set_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e2', 'source' => 'set_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ],
            ],
        ]);

        $run = app(WorkflowRunner::class)->run($workflow, ['input' => 'test']);

        $this->assertEquals('completed', $run->status);
        $this->assertEquals('Hello', $run->output['greeting'] ?? null);
        $this->assertGreaterThan(0, $run->steps()->count());
    }

    public function test_trace_emits_step_events_when_listener_provided(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Emitter Flow',
            'slug' => 'emitter-flow',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $events = [];

        app(WorkflowRunner::class)->run($workflow, ['input' => 'test'], function (string $event, array $data) use (&$events) {
            $events[] = [$event, $data];
        });

        $this->assertContains('step_started', array_column($events, 0));
        $this->assertContains('step_completed', array_column($events, 0));
    }

    public function test_native_output_includes_normalized_steps_from_graph(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Native Steps Flow',
            'slug' => 'native-steps-flow',
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'agent_1', 'type' => 'agent', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
                ],
                'edges' => [],
            ],
        ]);

        $runner = app(WorkflowRunner::class);
        $reflection = new \ReflectionClass($runner);

        $normalize = $reflection->getMethod('normalizeNativeSteps');
        $normalize->setAccessible(true);

        $outputWithSteps = $reflection->getMethod('outputWithNativeSteps');
        $outputWithSteps->setAccessible(true);

        $steps = [
            [
                'node_id' => 'agent_1',
                'node_type' => 'agent_1',
                'state_snapshot' => ['agent_response' => 'Done'],
                'duration_ms' => 12,
            ],
        ];

        $normalized = $normalize->invoke($runner, $steps, $workflow);
        $this->assertSame('agent', $normalized[0]['node_type']);

        $output = $outputWithSteps->invoke($runner, ['agent_response' => 'Done'], $steps, $workflow);
        $this->assertArrayHasKey('__steps', $output);
        $this->assertSame('agent', $output['__steps'][0]['node_type']);
    }

    public function test_run_existing_trace_completes(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Queued Set State Flow',
            'slug' => 'queued-set-state-flow',
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    ['id' => 'set_1', 'type' => 'set_state', 'position' => ['x' => 200, 'y' => 0], 'data' => ['key' => 'greeting', 'value' => 'Hello']],
                    ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 400, 'y' => 0], 'data' => []],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start_1', 'target' => 'set_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e2', 'source' => 'set_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ],
            ],
        ]);

        $thread = StudioThread::create([
            'id' => (string) Str::uuid(),
            'entity_type' => WorkflowDefinition::class,
            'entity_id' => $workflow->id,
        ]);

        $run = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'status' => 'queued',
            'input' => ['input' => 'test'],
            'started_at' => null,
        ]);

        $result = app(WorkflowRunner::class)->runExistingRun($run, $workflow, ['input' => 'test']);

        $this->assertSame($run->id, $result->id);
        $this->assertSame('completed', $result->status);
        $this->assertNotNull($result->started_at);
        $this->assertSame('Hello', $result->output['greeting'] ?? null);
        $this->assertSame(1, StudioRun::count());
    }
}
