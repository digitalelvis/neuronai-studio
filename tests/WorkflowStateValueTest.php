<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowStateValue;

class WorkflowStateValueTest extends TestCase
{
    protected function stateWith(array $data): BuilderWorkflowState
    {
        return new BuilderWorkflowState(new GraphContext([], []), null, $data);
    }

    public function test_get_resolves_nested_dot_notation_keys(): void
    {
        $state = $this->stateWith([
            'lead' => [
                'email' => 'user@example.com',
                'tier' => 'gold',
            ],
        ]);

        $this->assertSame('user@example.com', WorkflowStateValue::get($state, 'lead.email'));
        $this->assertSame('gold', WorkflowStateValue::get($state, 'lead.tier'));
    }

    public function test_get_resolves_simple_top_level_keys(): void
    {
        $state = $this->stateWith([
            'tier' => 'gold',
            'input' => 'hello',
        ]);

        $this->assertSame('gold', WorkflowStateValue::get($state, 'tier'));
        $this->assertSame('hello', WorkflowStateValue::get($state, 'input'));
    }

    public function test_get_returns_null_for_missing_keys(): void
    {
        $state = $this->stateWith([
            'lead' => ['email' => 'user@example.com'],
        ]);

        $this->assertNull(WorkflowStateValue::get($state, 'lead.phone'));
        $this->assertNull(WorkflowStateValue::get($state, 'missing'));
    }
}
