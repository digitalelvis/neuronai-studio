<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Jobs\RunWorkflowJob;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Models\WorkflowTrace;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

class WorkflowRunnerDispatchTest extends TestCase
{
    public function test_dispatch_creates_queued_trace_and_pushes_job(): void
    {
        config(['neuronai-studio.async_runs_enabled' => true]);

        Queue::fake();

        $workflow = $this->setStateWorkflow();

        $trace = app(WorkflowRunner::class)->dispatch($workflow, ['input' => 'test']);

        $this->assertSame('queued', $trace->status);
        $this->assertNull($trace->started_at);
        $this->assertSame(['input' => 'test'], $trace->input);

        Queue::assertPushed(RunWorkflowJob::class, function (RunWorkflowJob $job) use ($trace, $workflow) {
            return $job->traceId === $trace->id
                && $job->workflowId === $workflow->id
                && $job->input === ['input' => 'test'];
        });

        $this->assertSame(1, WorkflowTrace::count());
    }

    public function test_dispatch_throws_when_async_disabled(): void
    {
        config(['neuronai-studio.async_runs_enabled' => false]);

        $workflow = $this->setStateWorkflow();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Async workflow runs are disabled');

        app(WorkflowRunner::class)->dispatch($workflow, ['input' => 'test']);
    }

    protected function setStateWorkflow(): WorkflowDefinition
    {
        return WorkflowDefinition::create([
            'name' => 'Dispatch Set State Flow',
            'slug' => 'dispatch-set-state-flow-'.uniqid(),
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
    }
}
