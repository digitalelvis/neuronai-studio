<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Illuminate\Support\Str;

class WorkflowIntegrateResumeTest extends TestCase
{
    protected function humanWorkflow(): WorkflowDefinition
    {
        return WorkflowDefinition::create([
            'name' => 'Resume Human Flow',
            'slug' => 'resume-human-flow-'.uniqid(),
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

    protected function pausedTrace(WorkflowDefinition $workflow): StudioRun
    {
        $run = app(WorkflowRunner::class)->run($workflow, ['message' => 'start']);

        $this->assertSame('awaiting_input', $run->status);
        $this->assertSame('human_1', $run->awaiting_node_id);

        return $run;
    }

    #[DefineEnvironment('lightIntegrationMiddleware')]
    public function test_resume_completes_workflow_vercel(): void
    {
        $workflow = $this->humanWorkflow();
        $run = $this->pausedTrace($workflow);

        $response = $this->postJson(
            route('neuronai-studio.integrate.workflows.resume', [
                'trace' => $run->id,
                'protocol' => 'vercel'
            ]),
            ['message' => 'order-42'],
        );

        $response->assertOk();
        $response->assertHeader('x-vercel-ai-ui-message-stream', 'v1');

        $content = $response->streamedContent();
        $this->assertStringContainsString('"type":"text-delta"', $content);
        $this->assertStringContainsString('order-42', $content);
        $this->assertStringContainsString('[DONE]', $content);

        $run->refresh();
        $this->assertSame('completed', $run->status);
        $this->assertSame('order-42', $run->output['confirmed'] ?? null);
    }

    #[DefineEnvironment('lightIntegrationMiddleware')]
    public function test_resume_completes_workflow_agui(): void
    {
        $workflow = $this->humanWorkflow();
        $run = $this->pausedTrace($workflow);

        $response = $this->postJson(
            route('neuronai-studio.integrate.workflows.resume', [
                'trace' => $run->id,
                'protocol' => 'agui'
            ]),
            ['message' => 'order-99'],
        );

        $response->assertOk();

        $content = $response->streamedContent();
        $this->assertStringContainsString('RUN_STARTED', $content);
        $this->assertStringContainsString('order-99', $content);
        $this->assertStringContainsString('RUN_FINISHED', $content);

        $run->refresh();
        $this->assertSame('completed', $run->status);
    }

    #[DefineEnvironment('lightIntegrationMiddleware')]
    public function test_resume_returns_422_when_not_awaiting_input(): void
    {
        $workflow = $this->humanWorkflow();

        $thread = StudioThread::create([
            'id' => (string) Str::uuid(),
            'entity_type' => WorkflowDefinition::class,
            'entity_id' => $workflow->id,
        ]);

        $run = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'status' => 'completed',
            'input' => ['message' => 'done'],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $response = $this->postJson(
            route('neuronai-studio.integrate.workflows.resume', [
                'trace' => $run->id,
                'protocol' => 'vercel'
            ]),
            ['message' => 'too late'],
        );

        $response->assertStatus(422);
    }

    #[DefineEnvironment('lightIntegrationMiddleware')]
    public function test_resume_unknown_protocol_returns_404(): void
    {
        $workflow = $this->humanWorkflow();
        $run = $this->pausedTrace($workflow);

        $response = $this->postJson(
            route('neuronai-studio.integrate.workflows.resume', [
                'trace' => $run->id,
                'protocol' => 'bogus'
            ]),
            ['message' => 'order-42'],
        );

        $response->assertNotFound();
    }

    protected function lightIntegrationMiddleware($app): void
    {
        $app['config']->set('neuronai-studio.stream_adapters.middleware', [SubstituteBindings::class]);
    }
}
