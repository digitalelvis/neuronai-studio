<?php

namespace ElvisLopesDigital\NeuronAIStudio\Tests;

use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowDefinition;
use ElvisLopesDigital\NeuronAIStudio\Runtime\WorkflowRunner;

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
}
