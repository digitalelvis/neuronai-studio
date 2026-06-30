<?php

namespace ElvisLopesDigital\NeuronAIStudio\Tests;

use ElvisLopesDigital\NeuronAIStudio\Registry\ProviderRegistry;
use ElvisLopesDigital\NeuronAIStudio\Runtime\BuilderWorkflowState;
use ElvisLopesDigital\NeuronAIStudio\Runtime\GraphContext;
use ElvisLopesDigital\NeuronAIStudio\Runtime\MessageFactory;
use ElvisLopesDigital\NeuronAIStudio\Runtime\NodeExecutors\LlmNodeExecutor;
use Illuminate\Support\Facades\Storage;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Testing\RequestRecord;

class LlmNodeExecutorTest extends TestCase
{
    public function test_execute_passes_attachments_as_multimodal_message(): void
    {
        Storage::fake('local');
        config(['neuronai-studio.attachments.disk' => 'local']);

        $storageKey = 'neuronai-studio/attachments/test.png';
        Storage::disk('local')->put($storageKey, 'fake-image-bytes');

        $fakeProvider = new FakeAIProvider(new AssistantMessage('extracted'));
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($fakeProvider);

        $executor = new LlmNodeExecutor($registry, new MessageFactory);
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
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($fakeProvider);

        $executor = new LlmNodeExecutor($registry, new MessageFactory);
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
}
