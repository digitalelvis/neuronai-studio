<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

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
use NeuronAI\Chat\Messages\AssistantMessage;
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
}
