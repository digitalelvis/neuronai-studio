<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\StudioChatMessage;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Runtime\ChatContentStorage;
use DigitalElvis\NeuronAIStudio\Runtime\Memory\StudioEloquentChatHistory;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\Messages\StoredAttachmentContent;
use Illuminate\Support\Facades\Storage;
use NeuronAI\Chat\Enums\SourceType;
use NeuronAI\Chat\Messages\ContentBlocks\ImageContent;
use NeuronAI\Chat\Messages\UserMessage;

class ChatContentStorageTest extends TestCase
{
    public function test_serializes_image_block_as_storage_reference_without_base64(): void
    {
        $storage = new ChatContentStorage(new MessageFactory);

        $image = new ImageContent('aVeryLongBase64Payload', SourceType::BASE64, 'image/jpeg');
        $image->addMetadata('storage_key', 'neuronai-studio/attachments/sample.jpg');
        $image->addMetadata('attachment_type', 'image');
        $image->addMetadata('attachment_name', 'sample.jpg');

        $serialized = $storage->serializeForStorage([$image]);

        $this->assertSame('neuronai-studio/attachments/sample.jpg', $serialized[0]['storage_key'] ?? null);
        $this->assertArrayNotHasKey('content', $serialized[0]);
        $this->assertStringNotContainsString('aVeryLongBase64Payload', json_encode($serialized));
    }

    public function test_vision_false_strips_stored_attachment_from_inference_messages(): void
    {
        Storage::fake('local');
        config(['neuronai-studio.attachments.disk' => 'local']);

        $storageKey = 'neuronai-studio/attachments/test.jpg';
        Storage::disk('local')->put($storageKey, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        $storage = new ChatContentStorage(new MessageFactory);
        $message = new UserMessage([
            new StoredAttachmentContent($storageKey, 'image', 'image/jpeg', 'test.jpg'),
            new \NeuronAI\Chat\Messages\ContentBlocks\TextContent('Transcrição curta'),
        ]);

        $resolved = $storage->applyVisionPolicy([$message], visionEnabled: false);

        $this->assertNull($resolved[0]->getImage());
        $this->assertStringContainsString('Transcrição curta', (string) $resolved[0]->getContent());
    }

    public function test_vision_true_hydrates_stored_attachment_from_disk(): void
    {
        Storage::fake('local');
        config(['neuronai-studio.attachments.disk' => 'local']);

        $storageKey = 'neuronai-studio/attachments/test.jpg';
        Storage::disk('local')->put($storageKey, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        $storage = new ChatContentStorage(new MessageFactory);
        $message = new UserMessage([
            new StoredAttachmentContent($storageKey, 'image', 'image/jpeg', 'test.jpg'),
        ]);

        $resolved = $storage->applyVisionPolicy([$message], visionEnabled: true);

        $this->assertNotNull($resolved[0]->getImage());
    }

    public function test_eloquent_history_persists_storage_key_not_base64(): void
    {
        Storage::fake('local');
        config(['neuronai-studio.attachments.disk' => 'local']);

        $storageKey = 'neuronai-studio/attachments/persist.jpg';
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        Storage::disk('local')->put($storageKey, $png);

        $thread = StudioThread::query()->create();
        $history = new StudioEloquentChatHistory($thread->id, StudioChatMessage::class, contextWindow: 50000);
        $history->setMediaVisionEnabled(false);

        $factory = new MessageFactory;
        $userMessage = $factory->resolveMessageWithAttachments('Doc', [[
            'type' => 'image',
            'storage_key' => $storageKey,
            'mime_type' => 'image/jpeg',
            'name' => 'persist.jpg',
        ]]);

        $history->addMessage($userMessage);

        $record = StudioChatMessage::query()->where('thread_id', $thread->id)->first();
        $this->assertNotNull($record);
        $encoded = json_encode($record->content);
        $this->assertStringContainsString('storage_key', (string) $encoded);
        $this->assertStringNotContainsString('jpeg-bytes', (string) $encoded);
    }
}
