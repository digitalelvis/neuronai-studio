<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Http\Middleware\EnsureNeuronAIStudioAuthorized;
use DigitalElvis\NeuronAIStudio\Jobs\ResumeWorkflowJob;
use DigitalElvis\NeuronAIStudio\Jobs\RunWorkflowJob;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Models\WorkflowTrace;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

class WorkflowRunControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);
    }

    public function test_run_returns_202_with_queued_trace(): void
    {
        config(['neuronai-studio.async_runs_enabled' => true]);

        Queue::fake();

        $workflow = $this->setStateWorkflow();
        $threadId = (string) Str::uuid();

        $response = $this->postJson(route('neuronai-studio.workflows.run', $workflow), [
            'message' => 'hello async',
            'thread_id' => $threadId,
        ]);

        $response->assertAccepted();
        $response->assertJson([
            'status' => 'queued',
            'thread_id' => $threadId,
        ]);

        $traceId = $response->json('trace_id');
        $this->assertNotNull($traceId);

        $trace = WorkflowTrace::findOrFail($traceId);
        $this->assertSame('queued', $trace->status);
        $this->assertSame('hello async', $trace->input['message'] ?? null);

        Queue::assertPushed(RunWorkflowJob::class);
    }

    public function test_run_returns_501_when_async_disabled(): void
    {
        config(['neuronai-studio.async_runs_enabled' => false]);

        $workflow = $this->setStateWorkflow();

        $response = $this->postJson(route('neuronai-studio.workflows.run', $workflow), [
            'message' => 'hello sync only',
        ]);

        $response->assertStatus(501);
        $response->assertJsonFragment([
            'message' => 'Async workflow runs are disabled. Set NEURONAI_STUDIO_ASYNC_RUNS_ENABLED=true or use the synchronous stream endpoint.',
        ]);
    }

    public function test_run_validates_message_or_attachments(): void
    {
        config(['neuronai-studio.async_runs_enabled' => true]);

        $workflow = $this->setStateWorkflow();

        $response = $this->postJson(route('neuronai-studio.workflows.run', $workflow), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['message']);
    }

    public function test_resume_returns_202_with_queued_trace(): void
    {
        config(['neuronai-studio.async_runs_enabled' => true]);

        Queue::fake();

        $workflow = $this->humanWorkflow();
        $paused = app(WorkflowRunner::class)->run($workflow, ['message' => 'start']);

        $this->assertSame('awaiting_input', $paused->status);

        $response = $this->postJson(route('neuronai-studio.workflows.traces.resume', $paused), [
            'message' => 'order-42',
        ]);

        $response->assertAccepted();
        $response->assertJson([
            'trace_id' => $paused->id,
            'status' => 'queued',
        ]);

        $paused->refresh();
        $this->assertSame('queued', $paused->status);

        Queue::assertPushed(ResumeWorkflowJob::class, function (ResumeWorkflowJob $job) use ($paused) {
            return $job->traceId === $paused->id
                && $job->nodeId === 'human_1'
                && $job->message === 'order-42';
        });
    }

    public function test_resume_returns_501_when_async_disabled(): void
    {
        config(['neuronai-studio.async_runs_enabled' => false]);

        $workflow = $this->humanWorkflow();
        $paused = app(WorkflowRunner::class)->run($workflow, ['message' => 'start']);

        $response = $this->postJson(route('neuronai-studio.workflows.traces.resume', $paused), [
            'message' => 'order-42',
            'node_id' => 'human_1',
        ]);

        $response->assertStatus(501);
    }

    public function test_resume_returns_422_when_trace_not_awaiting_input(): void
    {
        config(['neuronai-studio.async_runs_enabled' => true]);

        $workflow = $this->setStateWorkflow();
        $trace = app(WorkflowRunner::class)->run($workflow, ['message' => 'done']);

        $response = $this->postJson(route('neuronai-studio.workflows.traces.resume', $trace), [
            'message' => 'too late',
        ]);

        $response->assertStatus(422);
    }

    public function test_resume_e2e_processes_job_to_completed(): void
    {
        config(['neuronai-studio.async_runs_enabled' => true]);

        Queue::fake();

        $workflow = $this->humanWorkflow();
        $paused = app(WorkflowRunner::class)->run($workflow, ['message' => 'start']);

        $response = $this->postJson(route('neuronai-studio.workflows.traces.resume', $paused), [
            'message' => 'order-42',
        ]);

        $response->assertAccepted();

        Queue::assertPushed(ResumeWorkflowJob::class, function (ResumeWorkflowJob $job) {
            $job->handle(app(WorkflowRunner::class));

            return true;
        });

        $paused->refresh();
        $this->assertSame('completed', $paused->status);
        $this->assertSame('order-42', $paused->output['order_id'] ?? null);
    }

    protected function humanWorkflow(): WorkflowDefinition
    {
        return WorkflowDefinition::create([
            'name' => 'Controller Human Flow',
            'slug' => 'controller-human-flow-'.uniqid(),
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

    protected function setStateWorkflow(): WorkflowDefinition
    {
        return WorkflowDefinition::create([
            'name' => 'Controller Set State Flow',
            'slug' => 'controller-set-state-flow-'.uniqid(),
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
