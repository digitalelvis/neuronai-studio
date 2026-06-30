<?php

namespace ElvisLopesDigital\NeuronAIStudio\Tests;

use ElvisLopesDigital\NeuronAIStudio\Http\Middleware\EnsureNeuronAIStudioAuthorized;
use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowDefinition;
use ElvisLopesDigital\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StudioTestHarnessTest extends TestCase
{
    public function test_workflow_run_merges_initial_state(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Context Flow',
            'slug' => 'context-flow',
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    ['id' => 'set_1', 'type' => 'set_state', 'position' => ['x' => 200, 'y' => 0], 'data' => ['key' => 'customer_id', 'from_key' => 'customer_id']],
                    ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 400, 'y' => 0], 'data' => []],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start_1', 'target' => 'set_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e2', 'source' => 'set_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ],
            ],
        ]);

        $trace = app(WorkflowRunner::class)->run($workflow, [
            'message' => 'hello',
            'state' => ['customer_id' => 'cust-123'],
        ]);

        $this->assertEquals('completed', $trace->status);
        $this->assertEquals('cust-123', $trace->output['customer_id'] ?? null);
    }

    public function test_human_node_pauses_and_resumes_trace(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Human Flow',
            'slug' => 'human-flow',
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

        $runner = app(WorkflowRunner::class);
        $events = [];

        $paused = $runner->run($workflow, ['message' => 'start'], function (string $event, array $data) use (&$events) {
            $events[] = [$event, $data];
        });

        $this->assertEquals('awaiting_input', $paused->status);
        $this->assertEquals('human_1', $paused->awaiting_node_id);
        $this->assertContains('human_input_required', array_column($events, 0));

        $completed = $runner->resume($paused, 'human_1', 'order-42', function (string $event, array $data) use (&$events) {
            $events[] = [$event, $data];
        });

        $this->assertEquals('completed', $completed->status);
        $this->assertEquals('order-42', $completed->output['order_id'] ?? null);
        $this->assertEquals('order-42', $completed->output['confirmed'] ?? null);
    }

    public function test_attachment_upload_returns_storage_key(): void
    {
        Storage::fake('local');
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $file = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');

        $response = $this->post(route('neuronai-studio.attachments.store'), [
            'file' => $file,
            'type' => 'document',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['storage_key', 'mime_type', 'name', 'type', 'url']);
        $this->assertStringContainsString('storage_key=', (string) $response->json('url'));
    }

    public function test_attachment_preview_route_serves_uploaded_file(): void
    {
        Storage::fake('local');
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $file = UploadedFile::fake()->image('preview.png');

        $upload = $this->post(route('neuronai-studio.attachments.store'), [
            'file' => $file,
            'type' => 'image',
        ]);

        $storageKey = (string) $upload->json('storage_key');

        $this->get(route('neuronai-studio.attachments.show', ['storage_key' => $storageKey]))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_workflow_stream_accepts_post_payload(): void
    {
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $workflow = WorkflowDefinition::create([
            'name' => 'Stream Flow',
            'slug' => 'stream-flow',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $response = $this->post(route('neuronai-studio.workflows.trace.stream', $workflow), [
            'message' => 'Testing via POST',
            'state' => ['locale' => 'pt-BR'],
        ]);

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('content-type'));
    }

    public function test_workflow_stream_rejects_attachment_without_storage_key(): void
    {
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $workflow = WorkflowDefinition::create([
            'name' => 'Attachment Validation Flow',
            'slug' => 'attachment-validation-flow',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $response = $this->postJson(route('neuronai-studio.workflows.trace.stream', $workflow), [
            'message' => 'See attached',
            'attachments' => [
                ['type' => 'image', 'name' => 'photo.png'],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['attachments.0.storage_key']);
    }

    public function test_workflow_resume_rejects_attachment_without_storage_key(): void
    {
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $workflow = WorkflowDefinition::create([
            'name' => 'Resume Attachment Flow',
            'slug' => 'resume-attachment-flow',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $trace = app(WorkflowRunner::class)->run($workflow, ['message' => 'start']);

        $response = $this->postJson(route('neuronai-studio.workflows.traces.resume.stream', $trace), [
            'message' => 'resume message',
            'node_id' => 'human_1',
            'attachments' => [
                ['type' => 'document', 'name' => 'notes.pdf'],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['attachments.0.storage_key']);
    }

    public function test_workflow_run_stores_attachments_in_state(): void
    {
        Storage::fake('local');
        config(['neuronai-studio.attachments.disk' => 'local']);

        $storageKey = 'neuronai-studio/attachments/notes.txt';
        Storage::disk('local')->put($storageKey, 'lead details');

        $workflow = WorkflowDefinition::create([
            'name' => 'Attachment State Flow',
            'slug' => 'attachment-state-flow',
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ],
            ],
        ]);

        $trace = app(WorkflowRunner::class)->run($workflow, [
            'message' => 'Qualify this lead',
            'attachments' => [
                [
                    'type' => 'document',
                    'storage_key' => $storageKey,
                    'mime_type' => 'text/plain',
                    'name' => 'notes.txt',
                ],
            ],
        ]);

        $this->assertEquals('completed', $trace->status);
        $this->assertSame($storageKey, $trace->output['attachments'][0]['storage_key'] ?? null);
    }
}
