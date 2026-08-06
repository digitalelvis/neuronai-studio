<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\SetStateNodeExecutor;
use NeuronAI\Workflow\WorkflowState;
use PHPUnit\Framework\Attributes\Test;

class SetStateNodeExecutorTest extends TestCase
{
    #[Test]
    public function it_writes_literal_value(): void
    {
        $state = new WorkflowState(['input' => 'hi']);
        $this->execute($state, ['key' => 'tier', 'value' => 'gold']);

        $this->assertSame('gold', $state->get('tier'));
    }

    #[Test]
    public function it_interpolates_templates_in_value(): void
    {
        $state = new WorkflowState([
            'input' => 'Alice',
            'account' => ['status' => 'active'],
        ]);
        $this->execute($state, [
            'key' => 'greeting',
            'value' => 'Hello {{input}}, status {{account.status}}',
        ]);

        $this->assertSame('Hello Alice, status active', $state->get('greeting'));
    }

    #[Test]
    public function it_copies_whole_value_via_legacy_from_key(): void
    {
        $state = new WorkflowState([
            'lead' => ['tier' => 'gold', 'email' => 'a@b.com'],
        ]);
        $this->execute($state, [
            'key' => 'profile',
            'value' => 'ignored',
            'from_key' => 'lead',
        ]);

        $this->assertSame(['tier' => 'gold', 'email' => 'a@b.com'], $state->get('profile'));
    }

    #[Test]
    public function it_appends_via_legacy_append_from_key(): void
    {
        $state = new WorkflowState([
            'notes' => 'step 1',
            'human_response' => 'step 2',
        ]);
        $this->execute($state, [
            'key' => 'notes',
            'append_from_key' => 'human_response',
        ]);

        $this->assertSame("step 1\nstep 2", $state->get('notes'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function execute(WorkflowState $state, array $data): void
    {
        $executor = new SetStateNodeExecutor;
        $executor->execute(
            ['id' => 'set_1', 'type' => 'set_state', 'data' => $data],
            $state,
            new GraphContext([], []),
        );
    }
}
