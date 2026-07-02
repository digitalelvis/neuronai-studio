<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Http\Middleware\EnsureNeuronAIStudioAuthorized;
use DigitalElvis\NeuronAIStudio\Jobs\RunWorkflowJob;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Models\WorkflowTrace;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

class WorkflowQueueRunnerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);
    }

    public function test_post_run_processes_job_to_completed_trace(): void
    {
        config(['neuronai-studio.async_runs_enabled' => true]);

        Queue::fake();

        $workflow = $this->setStateWorkflow();

        $response = $this->postJson(route('neuronai-studio.workflows.run', $workflow), [
            'message' => 'queue me',
        ]);

        $response->assertAccepted();

        $traceId = $response->json('trace_id');

        Queue::assertPushed(RunWorkflowJob::class, function (RunWorkflowJob $job) use ($traceId) {
            $this->assertSame($traceId, $job->traceId);
            $job->handle(app(WorkflowRunner::class));

            return true;
        });

        $trace = WorkflowTrace::findOrFail($traceId);
        $this->assertSame('completed', $trace->status);
        $this->assertSame('Hello', $trace->output['greeting'] ?? null);

        $poll = $this->getJson(route('neuronai-studio.workflows.traces.show.json', $trace));
        $poll->assertOk();
        $poll->assertJsonPath('trace.status', 'completed');
    }

    public function test_job_failure_marks_trace_failed(): void
    {
        $workflow = $this->setStateWorkflow();

        $trace = WorkflowTrace::create([
            'workflow_definition_id' => $workflow->id,
            'status' => 'queued',
            'input' => ['input' => 'test'],
            'started_at' => null,
        ]);

        $job = new RunWorkflowJob($trace->id, 999999, ['input' => 'test']);

        try {
            $job->handle(app(WorkflowRunner::class));
            $this->fail('Expected job handle to throw for missing workflow.');
        } catch (\Throwable) {
            // runExistingTrace never reached; job throws on findOrFail
        }

        $job->failed(new RuntimeException('Simulated permanent failure'));

        $trace->refresh();
        $this->assertSame('failed', $trace->status);
        $this->assertSame('Simulated permanent failure', $trace->error_message);
    }

    public function test_sync_stream_path_unchanged_when_async_disabled(): void
    {
        config(['neuronai-studio.async_runs_enabled' => false]);

        $workflow = $this->setStateWorkflow();

        $response = $this->postJson(route('neuronai-studio.workflows.run', $workflow), [
            'message' => 'should not queue',
        ]);

        $response->assertStatus(501);
        $this->assertSame(0, WorkflowTrace::count());
    }

    protected function setStateWorkflow(): WorkflowDefinition
    {
        return WorkflowDefinition::create([
            'name' => 'E2E Set State Flow',
            'slug' => 'e2e-set-state-flow-'.uniqid(),
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
