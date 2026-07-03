<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Runtime\Persistence\EloquentPersistence;
use DigitalElvis\NeuronAIStudio\Tests\Fixtures\SampleInterruptNode;
use NeuronAI\Exceptions\WorkflowException;
use NeuronAI\Workflow\Events\StartEvent;
use NeuronAI\Workflow\Interrupt\Action;
use NeuronAI\Workflow\Interrupt\ApprovalRequest;
use NeuronAI\Workflow\Interrupt\WorkflowInterrupt;
use NeuronAI\Workflow\WorkflowState;

class EloquentPersistenceTest extends TestCase
{
    protected function interrupt(string $workflowId): WorkflowInterrupt
    {
        return new WorkflowInterrupt(
            new ApprovalRequest('Please confirm', [new Action('submit', 'Submit', 'Confirm the step')]),
            new SampleInterruptNode,
            new WorkflowState(['__workflowId' => $workflowId, 'input' => 'hello']),
            new StartEvent,
        );
    }

    public function test_saves_and_loads_native_workflow_interrupt(): void
    {
        $persistence = new EloquentPersistence;
        $persistence->save('wf_1', $this->interrupt('wf_1'));

        $loaded = $persistence->load('wf_1');

        $this->assertSame('Please confirm', $loaded->getRequest()->getMessage());
        $this->assertSame('hello', $loaded->getState()->get('input'));
    }

    public function test_overwrites_existing_interrupt_for_same_workflow(): void
    {
        $persistence = new EloquentPersistence;
        $persistence->save('wf_1', $this->interrupt('wf_1'));
        $persistence->save('wf_1', $this->interrupt('wf_1'));

        $this->assertSame(1, \DigitalElvis\NeuronAIStudio\Models\WorkflowCheckpoint::query()
            ->where('workflow_key', 'wf_1')->count());
    }

    public function test_delete_removes_interrupt(): void
    {
        $persistence = new EloquentPersistence;
        $persistence->save('wf_1', $this->interrupt('wf_1'));
        $persistence->delete('wf_1');

        $this->expectException(WorkflowException::class);
        $persistence->load('wf_1');
    }

    public function test_load_missing_throws(): void
    {
        $this->expectException(WorkflowException::class);
        (new EloquentPersistence)->load('missing');
    }
}
