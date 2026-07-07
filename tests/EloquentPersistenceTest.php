<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Runtime\Persistence\EloquentPersistence;
use DigitalElvis\NeuronAIStudio\Tests\Fixtures\SampleInterruptNode;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use NeuronAI\Exceptions\WorkflowException;
use NeuronAI\Workflow\Events\StartEvent;
use NeuronAI\Workflow\Interrupt\Action;
use NeuronAI\Workflow\Interrupt\ApprovalRequest;
use NeuronAI\Workflow\Interrupt\WorkflowInterrupt;
use NeuronAI\Workflow\WorkflowState;
use Illuminate\Support\Str;

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

    protected function createRun(string $id): StudioRun
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Persistence Flow',
            'slug' => 'persistence-flow-' . uniqid(),
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $thread = StudioThread::create([
            'id' => (string) Str::uuid(),
            'entity_type' => WorkflowDefinition::class,
            'entity_id' => $workflow->id,
        ]);

        return StudioRun::create([
            'id' => $id,
            'thread_id' => $thread->id,
            'status' => 'running',
            'input' => [],
        ]);
    }

    public function test_saves_and_loads_native_workflow_interrupt(): void
    {
        $runId = (string) Str::uuid();
        $this->createRun($runId);

        $persistence = new EloquentPersistence;
        $persistence->save($runId, $this->interrupt($runId));

        $loaded = $persistence->load($runId);

        $this->assertSame('Please confirm', $loaded->getRequest()->getMessage());
        $this->assertSame('hello', $loaded->getState()->get('input'));
    }

    public function test_overwrites_existing_interrupt_for_same_workflow(): void
    {
        $runId = (string) Str::uuid();
        $run = $this->createRun($runId);

        $persistence = new EloquentPersistence;
        $persistence->save($runId, $this->interrupt($runId));
        $persistence->save($runId, $this->interrupt($runId));

        $run->refresh();
        $this->assertNotNull($run->checkpoint_state['interrupt'] ?? null);
        $this->assertSame(1, StudioRun::where('id', $runId)->count());
    }

    public function test_delete_removes_interrupt(): void
    {
        $runId = (string) Str::uuid();
        $this->createRun($runId);

        $persistence = new EloquentPersistence;
        $persistence->save($runId, $this->interrupt($runId));
        $persistence->delete($runId);

        $this->expectException(WorkflowException::class);
        $persistence->load($runId);
    }

    public function test_load_missing_throws(): void
    {
        $this->expectException(WorkflowException::class);
        (new EloquentPersistence)->load('missing');
    }
}
