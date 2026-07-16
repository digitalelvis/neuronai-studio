<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\StudioTrace;
use DigitalElvis\NeuronAIStudio\Models\StudioTraceSpan;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\LlmNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\StructuredOutput\StructuredOutputResolver;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Tests\Fixtures\Output\SampleLeadProfile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Testing\RequestRecord;

class LlmNodeExecutorTest extends TestCase
{
    protected function fixtureScanConfig(): void
    {
        $fixturesPath = __DIR__.'/Fixtures';

        config([
            'neuronai-studio.export_path' => $fixturesPath,
            'neuronai-studio.export_namespace' => 'DigitalElvis\\NeuronAIStudio\\Tests\\Fixtures',
            'neuronai-studio.structured_output_scan_paths' => [$fixturesPath.'/Output'],
        ]);
    }

    protected function makeExecutor(FakeAIProvider $fakeProvider): LlmNodeExecutor
    {
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($fakeProvider);

        $runner = new AgentRunner(
            $registry,
            $this->createMock(ToolResolver::class),
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );

        return new LlmNodeExecutor(
            $registry,
            $runner,
            new MessageFactory,
            app(StructuredOutputResolver::class),
        );
    }

    public function test_execute_passes_attachments_as_multimodal_message(): void
    {
        Storage::fake('local');
        config(['neuronai-studio.attachments.disk' => 'local']);

        $storageKey = 'neuronai-studio/attachments/test.png';
        Storage::disk('local')->put($storageKey, 'fake-image-bytes');

        $fakeProvider = new FakeAIProvider(new AssistantMessage('extracted'));
        $executor = $this->makeExecutor($fakeProvider);
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, [
            'attachments' => [
                [
                    'type' => 'image',
                    'storage_key' => $storageKey,
                    'mime_type' => 'image/png',
                    'name' => 'test.png',
                ],
            ],
        ]);

        $executor->execute([
            'data' => [
                'prompt' => 'Describe this image',
                'provider' => 'openai',
                'model' => 'gpt-4o',
                'output_key' => 'llm_response',
            ],
        ], $state, $context);

        $this->assertSame('extracted', $state->get('llm_response'));

        $fakeProvider->assertSent(function (RequestRecord $record): bool {
            $message = $record->messages[0] ?? null;

            return $message !== null && $message->getImage() !== null;
        });
    }

    public function test_execute_without_attachments_sends_text_only_message(): void
    {
        $fakeProvider = new FakeAIProvider(new AssistantMessage('text only'));
        $executor = $this->makeExecutor($fakeProvider);
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, []);

        $executor->execute([
            'data' => [
                'prompt' => 'Hello',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'output_key' => 'llm_response',
            ],
        ], $state, $context);

        $fakeProvider->assertSent(function (RequestRecord $record): bool {
            $message = $record->messages[0] ?? null;

            return $message !== null && $message->getImage() === null;
        });
    }

    public function test_execute_structured_stores_validated_output_in_state(): void
    {
        $this->fixtureScanConfig();

        $fakeProvider = new FakeAIProvider(
            new AssistantMessage('{"email": "alice@example.com", "tier": "gold"}'),
        );
        $executor = $this->makeExecutor($fakeProvider);
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, []);

        $executor->execute([
            'data' => [
                'prompt' => 'Extract lead',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'output_key' => 'lead',
                'structured' => true,
                'output_class' => SampleLeadProfile::class,
            ],
        ], $state, $context);

        $this->assertSame([
            'email' => 'alice@example.com',
            'tier' => 'gold',
        ], $state->get('lead'));

        $fakeProvider->assertMethodCallCount('structured', 1);
        $fakeProvider->assertMethodCallCount('chat', 0);
    }

    public function test_execute_structured_false_uses_chat_path(): void
    {
        $fakeProvider = new FakeAIProvider(new AssistantMessage('plain text'));
        $executor = $this->makeExecutor($fakeProvider);
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, []);

        $executor->execute([
            'data' => [
                'prompt' => 'Hello',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'output_key' => 'llm_response',
                'structured' => false,
            ],
        ], $state, $context);

        $this->assertSame('plain text', $state->get('llm_response'));
        $fakeProvider->assertMethodCallCount('chat', 1);
        $fakeProvider->assertMethodCallCount('structured', 0);
    }

    public function test_execute_chat_records_llm_span_on_parent_workflow_run(): void
    {
        config([
            'neuronai-studio.usage.pricing.openai.gpt-4o-mini' => [
                'prompt_per_1k' => 0.00015,
                'completion_per_1k' => 0.0006,
            ],
        ]);

        $parent = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => StudioThread::create(['id' => (string) Str::uuid()])->id,
            'status' => 'running',
        ]);
        $trace = StudioTrace::create([
            'id' => (string) Str::uuid(),
            'run_id' => $parent->id,
        ]);

        $fakeProvider = new FakeAIProvider(
            (new AssistantMessage('metered'))->setUsage(new Usage(1000, 500)),
        );
        $executor = $this->makeExecutor($fakeProvider);
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, [
            '__studio_run_id' => $parent->id,
            '__studio_trace_id' => $trace->id,
        ]);

        $executor->execute([
            'data' => [
                'prompt' => 'Hello',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'output_key' => 'llm_response',
            ],
        ], $state, $context);

        $span = StudioTraceSpan::query()
            ->where('trace_id', $trace->id)
            ->where('type', 'llm')
            ->first();

        $this->assertNotNull($span);
        $this->assertSame('openai', $span->provider);
        $this->assertSame('gpt-4o-mini', $span->model);
        $this->assertSame(1000, $span->prompt_tokens);
        $this->assertSame(500, $span->completion_tokens);
        $this->assertSame('0.000450', $span->estimated_cost);
        $this->assertSame(1500, $parent->fresh()->total_tokens);
        $this->assertSame('0.000450', $parent->fresh()->estimated_cost);
    }

    public function test_execute_chat_without_parent_ids_does_not_throw(): void
    {
        $fakeProvider = new FakeAIProvider(
            (new AssistantMessage('ok'))->setUsage(new Usage(10, 5)),
        );
        $executor = $this->makeExecutor($fakeProvider);
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, []);

        $executor->execute([
            'data' => [
                'prompt' => 'Hello',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'output_key' => 'llm_response',
            ],
        ], $state, $context);

        $this->assertSame('ok', $state->get('llm_response'));
        $this->assertSame(0, StudioTraceSpan::query()->where('type', 'llm')->count());
    }

    public function test_execute_stream_records_usage_from_final_message(): void
    {
        config([
            'neuronai-studio.usage.pricing.openai.gpt-4o-mini' => [
                'prompt_per_1k' => 0.00015,
                'completion_per_1k' => 0.0006,
            ],
        ]);

        $parent = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => StudioThread::create(['id' => (string) Str::uuid()])->id,
            'status' => 'running',
        ]);
        $trace = StudioTrace::create([
            'id' => (string) Str::uuid(),
            'run_id' => $parent->id,
        ]);

        $fakeProvider = new FakeAIProvider(
            (new AssistantMessage('streamed'))->setUsage(new Usage(2000, 0)),
        );
        $fakeProvider->setStreamChunkSize(4);
        $executor = $this->makeExecutor($fakeProvider);
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, [
            '__studio_run_id' => $parent->id,
            '__studio_trace_id' => $trace->id,
        ]);
        $state->stepEmitter = static function (): void {};

        $executor->execute([
            'id' => 'llm-1',
            'data' => [
                'prompt' => 'Hello',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'output_key' => 'llm_response',
                'stream' => true,
            ],
        ], $state, $context);

        $span = StudioTraceSpan::query()
            ->where('trace_id', $trace->id)
            ->where('type', 'llm')
            ->first();

        $this->assertNotNull($span);
        $this->assertSame(2000, $span->prompt_tokens);
        $this->assertSame('0.000300', $span->estimated_cost);
        $this->assertSame('streamed', $state->get('llm_response'));
    }
}
