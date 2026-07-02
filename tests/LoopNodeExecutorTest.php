<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\MaxLoopIterationsException;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\LoopNodeExecutor;

class LoopNodeExecutorTest extends TestCase
{
    /** @return array{0: LoopNodeExecutor, 1: BuilderWorkflowState} */
    protected function executorWithState(array $stateData = []): array
    {
        $context = new GraphContext([], []);

        return [
            new LoopNodeExecutor,
            new BuilderWorkflowState($context, null, $stateData),
        ];
    }

    protected function runLoop(array $nodeData, array $stateData = [], string $nodeId = 'loop_1'): string
    {
        [$executor, $state] = $this->executorWithState($stateData);

        return $executor->execute([
            'id' => $nodeId,
            'data' => $nodeData,
        ], $state, $state->graphContext);
    }

    public function test_exit_condition_with_dot_notation_routes_exit(): void
    {
        $this->assertSame('exit', $this->runLoop(
            [
                'max_steps' => 10,
                'state_key' => 'lead.tier',
                'operator' => 'equals',
                'value' => 'gold',
            ],
            ['lead' => ['tier' => 'gold']],
        ));
    }

    public function test_exit_condition_with_dot_notation_routes_continue(): void
    {
        $this->assertSame('continue', $this->runLoop(
            [
                'max_steps' => 10,
                'state_key' => 'lead.tier',
                'operator' => 'equals',
                'value' => 'gold',
            ],
            ['lead' => ['tier' => 'silver']],
        ));
    }

    public function test_exceeding_max_steps_throws_guardrail_exception(): void
    {
        $stateData = ['lead' => ['tier' => 'silver']];
        $nodeData = [
            'max_steps' => 2,
            'state_key' => 'lead.tier',
            'operator' => 'equals',
            'value' => 'gold',
        ];

        $this->assertSame('continue', $this->runLoop($nodeData, $stateData));

        $this->expectException(MaxLoopIterationsException::class);
        $this->runLoop($nodeData, array_merge($stateData, [
            '__loop_iterations.loop_1' => 2,
        ]));
    }
}
