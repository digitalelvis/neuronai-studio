<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\GraphExecutionLoop;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\LoopNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\NodeExecutorRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\SetStateNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\StartNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\StopNodeExecutor;

class GraphExecutionLoopTest extends TestCase
{
    public function test_emits_loop_iteration_event(): void
    {
        $graph = $this->simpleLoopGraph();
        $context = new GraphContext($graph['nodes'], $graph['edges']);
        $state = new BuilderWorkflowState($context, 1, ['ready' => 'yes']);
        $events = [];

        $state->stepEmitter = function (string $event, array $data) use (&$events) {
            $events[] = ['event' => $event, 'data' => $data];
        };

        $registry = new NodeExecutorRegistry;
        $registry->register('start', new StartNodeExecutor);
        $registry->register('stop', new StopNodeExecutor);
        $registry->register('set_state', new SetStateNodeExecutor);
        $registry->register('loop', new LoopNodeExecutor);

        $loop = new GraphExecutionLoop($registry);
        $loop->runFromNode('loop_1', $context, $state);

        $iterationEvents = array_values(array_filter(
            $events,
            fn (array $item) => $item['event'] === 'loop_iteration',
        ));

        $this->assertNotEmpty($iterationEvents);
        $this->assertSame('loop_1', $iterationEvents[0]['data']['node_id'] ?? null);
        $this->assertSame(1, $iterationEvents[0]['data']['iteration'] ?? null);
    }

    public function test_global_max_steps_guardrail_stops_infinite_execution(): void
    {
        config(['neuronai-studio.loop.global_max_steps' => 3]);

        $graph = $this->infiniteLoopGraph();
        $context = new GraphContext($graph['nodes'], $graph['edges']);
        $state = new BuilderWorkflowState($context, 1);

        $registry = new NodeExecutorRegistry;
        $registry->register('start', new StartNodeExecutor);
        $registry->register('stop', new StopNodeExecutor);
        $registry->register('set_state', new SetStateNodeExecutor);
        $registry->register('loop', new LoopNodeExecutor);

        $loop = new GraphExecutionLoop($registry);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('global max steps');

        $loop->runFromNode('loop_1', $context, $state);
    }

    /** @return array<string, mixed> */
    protected function simpleLoopGraph(): array
    {
        return [
            'nodes' => [
                ['id' => 'loop_1', 'type' => 'loop', 'data' => [
                    'max_steps' => 5,
                    'state_key' => 'ready',
                    'operator' => 'equals',
                    'value' => 'yes',
                ]],
                ['id' => 'set_1', 'type' => 'set_state', 'data' => ['key' => 'branch', 'value' => 'continued']],
                ['id' => 'stop_1', 'type' => 'stop', 'data' => []],
            ],
            'edges' => [
                ['source' => 'loop_1', 'target' => 'set_1', 'sourceHandle' => 'continue'],
                ['source' => 'set_1', 'target' => 'loop_1', 'sourceHandle' => 'default'],
                ['source' => 'loop_1', 'target' => 'stop_1', 'sourceHandle' => 'exit'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function infiniteLoopGraph(): array
    {
        return [
            'nodes' => [
                ['id' => 'loop_1', 'type' => 'loop', 'data' => [
                    'max_steps' => 100,
                    'state_key' => 'ready',
                    'operator' => 'equals',
                    'value' => 'yes',
                ]],
                ['id' => 'set_1', 'type' => 'set_state', 'data' => ['key' => 'tick', 'value' => '1']],
                ['id' => 'stop_1', 'type' => 'stop', 'data' => []],
            ],
            'edges' => [
                ['source' => 'loop_1', 'target' => 'set_1', 'sourceHandle' => 'continue'],
                ['source' => 'set_1', 'target' => 'loop_1', 'sourceHandle' => 'default'],
                ['source' => 'loop_1', 'target' => 'stop_1', 'sourceHandle' => 'exit'],
            ],
        ];
    }
}
