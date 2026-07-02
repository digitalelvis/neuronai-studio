<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\MaxLoopIterationsException;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\LoopNodeExecutor;

class LoopNodeExecutorTest extends TestCase
{
    /** @return array{0: LoopNodeExecutor, 1: BuilderWorkflowState} */
    protected function executorWithState(array $stateData = [], string $nodeId = 'loop_1'): array
    {
        $context = new GraphContext([], []);

        return [
            new LoopNodeExecutor,
            new BuilderWorkflowState($context, null, $stateData),
        ];
    }

    protected function runLoop(array $nodeData, array $stateData = [], string $nodeId = 'loop_1'): string
    {
        [$executor, $state] = $this->executorWithState($stateData, $nodeId);

        return $executor->execute(['id' => $nodeId, 'data' => $nodeData], $state, $state->graphContext);
    }

    public function test_routes_continue_when_condition_not_met(): void
    {
        $handle = $this->runLoop([
            'max_steps' => 3,
            'state_key' => 'ready',
            'operator' => 'equals',
            'value' => 'yes',
        ], ['ready' => 'no']);

        $this->assertSame('continue', $handle);
    }

    public function test_routes_exit_when_condition_met(): void
    {
        $handle = $this->runLoop([
            'max_steps' => 3,
            'state_key' => 'ready',
            'operator' => 'equals',
            'value' => 'yes',
        ], ['ready' => 'yes']);

        $this->assertSame('exit', $handle);
    }

    public function test_increments_iteration_counters_in_state(): void
    {
        [$executor, $state] = $this->executorWithState();
        $config = [
            'id' => 'loop_1',
            'data' => [
                'max_steps' => 5,
                'state_key' => 'ready',
                'operator' => 'equals',
                'value' => 'yes',
            ],
        ];

        $executor->execute($config, $state, $state->graphContext);
        $executor->execute($config, $state, $state->graphContext);

        $this->assertSame(2, $state->get('__loop_iterations.loop_1'));
        $this->assertSame(['loop_1' => 2], $state->get('__loop_iterations'));
    }

    public function test_throws_when_max_steps_exceeded(): void
    {
        $this->expectException(MaxLoopIterationsException::class);
        $this->expectExceptionMessage('Max loop iterations exceeded');

        $this->runLoop([
            'max_steps' => 1,
            'state_key' => 'ready',
            'operator' => 'equals',
            'value' => 'yes',
        ], [
            '__loop_iterations.loop_1' => 1,
        ]);
    }
}
