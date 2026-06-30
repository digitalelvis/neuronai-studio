<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Http\Middleware\EnsureNeuronAIStudioAuthorized;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use Illuminate\Support\Facades\Storage;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;

class MessageFactoryTest extends TestCase
{
    public function test_user_message_throws_when_attachment_file_is_missing(): void
    {
        Storage::fake('local');
        config(['neuronai-studio.attachments.disk' => 'local']);

        $factory = new MessageFactory;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read uploaded attachment');

        $factory->userMessage('Describe this', [
            [
                'type' => 'image',
                'storage_key' => 'neuronai-studio/attachments/missing.jpg',
                'mime_type' => 'image/jpeg',
                'name' => 'missing.jpg',
            ],
        ]);
    }

    public function test_resolve_message_with_attachments_uses_default_prompt_when_message_empty(): void
    {
        Storage::fake('local');
        config(['neuronai-studio.attachments.disk' => 'local']);

        $storageKey = 'neuronai-studio/attachments/photo.png';
        Storage::disk('local')->put(
            $storageKey,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
        );

        $factory = new MessageFactory;
        $message = $factory->resolveMessageWithAttachments('', [
            [
                'type' => 'image',
                'storage_key' => $storageKey,
                'mime_type' => 'image/png',
                'name' => 'photo.png',
            ],
        ]);

        $this->assertStringContainsString(MessageFactory::ATTACHMENT_ONLY_PROMPT, (string) $message->getContent());
        $this->assertNotNull($message->getImage());
    }

    public function test_validate_stored_attachments_reports_missing_files(): void
    {
        Storage::fake('local');
        config(['neuronai-studio.attachments.disk' => 'local']);

        $factory = new MessageFactory;

        $error = $factory->validateStoredAttachments([
            [
                'type' => 'image',
                'storage_key' => 'neuronai-studio/attachments/missing.jpg',
                'name' => 'photo.jpg',
            ],
        ]);

        $this->assertStringContainsString('photo.jpg', (string) $error);
    }

    public function test_agent_stream_accepts_attachment_only_payload(): void
    {
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);
        Storage::fake('local');
        config(['neuronai-studio.attachments.disk' => 'local']);

        $storageKey = 'neuronai-studio/attachments/photo.png';
        Storage::disk('local')->put(
            $storageKey,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
        );

        $agent = AgentDefinition::create([
            'name' => 'Vision Agent',
            'slug' => 'vision-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn(new FakeAIProvider(new AssistantMessage('I see the image.')));

        $this->app->instance(AgentRunner::class, new AgentRunner(
            $registry,
            $this->createMock(ToolResolver::class),
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        ));

        $response = $this->post(route('neuronai-studio.agents.chat.stream', $agent), [
            'message' => '',
            'attachments' => [
                [
                    'type' => 'image',
                    'storage_key' => $storageKey,
                    'mime_type' => 'image/png',
                    'name' => 'photo.png',
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertStringContainsString('event: done', $response->streamedContent());
    }

    public function test_agent_stream_rejects_empty_message_without_attachments(): void
    {
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $agent = AgentDefinition::create([
            'name' => 'Empty Payload Agent',
            'slug' => 'empty-payload-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        $response = $this->postJson(route('neuronai-studio.agents.chat.stream', $agent), [
            'message' => '',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['message']);
    }
}
