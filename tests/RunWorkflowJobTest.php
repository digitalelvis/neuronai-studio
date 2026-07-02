<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Jobs\RunWorkflowJob;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Models\WorkflowTrace;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

class RunWorkflowJobTest extends TestCase
{
    public function test_handle_runs_existing_trace_to_completion(): void
    {
        $workflow = $this->setStateWorkflow();

        $trace = WorkflowTrace::create([
            'workflow_definition_id' => $workflow->id,
            'status' => 'queued',
            'input' => ['input' => 'test'],
            'started_at' => null,
        ]);

        $job = new RunWorkflowJob($trace->id, $workflow->id, ['input' => 'test']);
        $job->handle(app(WorkflowRunner::class));

        $trace->refresh();
        $this->assertSame('completed', $trace->status);
        $this->assertSame('Hello', $trace->output['greeting'] ?? null);
        $this->assertNotNull($trace->started_at);
        $this->assertNotNull($trace->finished_at);
    }

    public function test_failed_marks_trace_as_failed(): void
    {
        $workflow = $this->setStateWorkflow();

        $trace = WorkflowTrace::create([
            'workflow_definition_id' => $workflow->id,
            'status' => 'running',
            'input' => ['input' => 'test'],
            'started_at' => now(),
        ]);

        $job = new RunWorkflowJob($trace->id, $workflow->id, ['input' => 'test']);
        $job->failed(new RuntimeException('Queue worker exploded'));

        $trace->refresh();
        $this->assertSame('failed', $trace->status);
        $this->assertSame('Queue worker exploded', $trace->error_message);
        $this->assertNotNull($trace->finished_at);
    }

    public function test_job_uses_configured_queue_and_retries(): void
    {
        config([
            'neuronai-studio.queue' => 'workflows',
            'neuronai-studio.queue_connection' => 'redis',
            'neuronai-studio.queue_tries' => 3,
            'neuronai-studio.queue_backoff' => 45,
        ]);

        $job = new RunWorkflowJob(1, 1, []);

        $this->assertSame('workflows', $job->queue);
        $this->assertSame('redis', $job->connection);
        $this->assertSame(3, $job->tries);
        $this->assertSame([45], $job->backoff());
    }

    public function test_dispatch_uses_configured_queue(): void
    {
        config([
            'neuronai-studio.async_runs_enabled' => true,
            'neuronai-studio.queue' => 'workflows',
        ]);

        Queue::fake();

        $workflow = $this->setStateWorkflow();
        app(WorkflowRunner::class)->dispatch($workflow, ['input' => 'test']);

        Queue::assertPushedOn('workflows', RunWorkflowJob::class);
    }

    protected function setStateWorkflow(): WorkflowDefinition
    {
        return WorkflowDefinition::create([
            'name' => 'Job Set State Flow',
            'slug' => 'job-set-state-flow-'.uniqid(),
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
