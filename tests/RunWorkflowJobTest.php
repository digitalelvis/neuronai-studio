<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Jobs\RunWorkflowJob;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;

class RunWorkflowJobTest extends TestCase
{
    public function test_handle_runs_existing_trace_to_completion(): void
    {
        $workflow = $this->setStateWorkflow();

        $thread = StudioThread::create([
            'id' => (string) Str::uuid(),
            'entity_type' => WorkflowDefinition::class,
            'entity_id' => $workflow->id,
        ]);

        $run = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'status' => 'queued',
            'input' => ['input' => 'test'],
            'started_at' => null,
        ]);

        $job = new RunWorkflowJob($run->id, $workflow->id, ['input' => 'test']);
        $job->handle(app(WorkflowRunner::class));

        $run->refresh();
        $this->assertSame('completed', $run->status);
        $this->assertSame('Hello', $run->output['greeting'] ?? null);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->finished_at);
    }

    public function test_failed_marks_trace_as_failed(): void
    {
        $workflow = $this->setStateWorkflow();

        $thread = StudioThread::create([
            'id' => (string) Str::uuid(),
            'entity_type' => WorkflowDefinition::class,
            'entity_id' => $workflow->id,
        ]);

        $run = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'status' => 'running',
            'input' => ['input' => 'test'],
            'started_at' => now(),
        ]);

        $job = new RunWorkflowJob($run->id, $workflow->id, ['input' => 'test']);
        $job->failed(new RuntimeException('Queue worker exploded'));

        $run->refresh();
        $this->assertSame('failed', $run->status);
        $this->assertSame('Queue worker exploded', $run->error_message);
        $this->assertNotNull($run->finished_at);
    }

    public function test_job_uses_configured_queue_and_retries(): void
    {
        config([
            'neuronai-studio.queue' => 'workflows',
            'neuronai-studio.queue_connection' => 'redis',
            'neuronai-studio.queue_tries' => 3,
            'neuronai-studio.queue_backoff' => 45,
        ]);

        $job = new RunWorkflowJob((string) Str::uuid(), 1, []);

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
