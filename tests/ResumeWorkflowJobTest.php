<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Http\Middleware\EnsureNeuronAIStudioAuthorized;
use DigitalElvis\NeuronAIStudio\Jobs\ResumeWorkflowJob;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;

class ResumeWorkflowJobTest extends TestCase
{
    public function test_handle_resumes_trace_to_completion(): void
    {
        $workflow = $this->humanWorkflow();
        $runner = app(WorkflowRunner::class);

        $paused = $runner->run($workflow, ['message' => 'start']);

        $this->assertSame('awaiting_input', $paused->status);
        $this->assertSame('human_1', $paused->awaiting_node_id);

        $paused->update(['status' => 'queued']);

        $job = new ResumeWorkflowJob($paused->id, 'human_1', 'order-42');
        $job->handle($runner);

        $paused->refresh();
        $this->assertSame('completed', $paused->status);
        $this->assertSame('order-42', $paused->output['order_id'] ?? null);
        $this->assertSame('order-42', $paused->output['confirmed'] ?? null);
        $this->assertNotNull($paused->finished_at);
    }

    public function test_failed_marks_trace_as_failed(): void
    {
        $workflow = $this->humanWorkflow();
        $runner = app(WorkflowRunner::class);
        $paused = $runner->run($workflow, ['message' => 'start']);

        $job = new ResumeWorkflowJob($paused->id, 'human_1', 'order-42');
        $job->failed(new RuntimeException('Resume worker exploded'));

        $paused->refresh();
        $this->assertSame('failed', $paused->status);
        $this->assertSame('Resume worker exploded', $paused->error_message);
        $this->assertNotNull($paused->finished_at);
    }

    public function test_job_uses_configured_queue_and_retries(): void
    {
        config([
            'neuronai-studio.queue' => 'workflows',
            'neuronai-studio.queue_connection' => 'redis',
            'neuronai-studio.queue_tries' => 3,
            'neuronai-studio.queue_backoff' => 45,
        ]);

        $job = new ResumeWorkflowJob((string) Str::uuid(), 'human_1', 'hello');

        $this->assertSame('workflows', $job->queue);
        $this->assertSame('redis', $job->connection);
        $this->assertSame(3, $job->tries);
        $this->assertSame([45], $job->backoff());
    }

    public function test_dispatch_resume_uses_configured_queue(): void
    {
        config([
            'neuronai-studio.async_runs_enabled' => true,
            'neuronai-studio.queue' => 'workflows',
        ]);

        Queue::fake();

        $workflow = $this->humanWorkflow();
        $runner = app(WorkflowRunner::class);
        $paused = $runner->run($workflow, ['message' => 'start']);

        $runner->dispatchResume($paused, 'human_1', 'order-42');

        Queue::assertPushedOn('workflows', ResumeWorkflowJob::class);
    }

    public function test_dispatch_resume_throws_when_async_disabled(): void
    {
        config(['neuronai-studio.async_runs_enabled' => false]);

        $workflow = $this->humanWorkflow();
        $runner = app(WorkflowRunner::class);
        $paused = $runner->run($workflow, ['message' => 'start']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Async workflow runs are disabled');

        $runner->dispatchResume($paused, 'human_1', 'order-42');
    }

    protected function humanWorkflow(): WorkflowDefinition
    {
        return WorkflowDefinition::create([
            'name' => 'Job Human Flow',
            'slug' => 'job-human-flow-'.uniqid(),
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    ['id' => 'human_1', 'type' => 'human', 'position' => ['x' => 200, 'y' => 0], 'data' => [
                        'prompt' => 'Confirm order id',
                        'output_key' => 'order_id',
                    ]],
                    ['id' => 'set_1', 'type' => 'set_state', 'position' => ['x' => 400, 'y' => 0], 'data' => [
                        'key' => 'confirmed',
                        'from_key' => 'order_id',
                    ]],
                    ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 600, 'y' => 0], 'data' => []],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start_1', 'target' => 'human_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e2', 'source' => 'human_1', 'target' => 'set_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e3', 'source' => 'set_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ],
            ],
        ]);
    }
}
