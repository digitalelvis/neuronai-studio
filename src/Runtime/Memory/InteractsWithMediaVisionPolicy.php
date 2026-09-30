<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Memory;

use DigitalElvis\NeuronAIStudio\Runtime\ChatContentStorage;
use DigitalElvis\NeuronAIStudio\Runtime\Messages\StoredAttachmentContent;
use NeuronAI\Chat\Messages\ContentBlocks\ContentBlockInterface;
use NeuronAI\Chat\Messages\Message;

trait InteractsWithMediaVisionPolicy
{
    protected bool $mediaVisionEnabled = true;

    public function setMediaVisionEnabled(bool $enabled): static
    {
        $this->mediaVisionEnabled = $enabled;

        return $this;
    }

    public function getMessages(): array
    {
        /** @var Message[] $messages */
        $messages = $this->history;

        return app(ChatContentStorage::class)->applyVisionPolicy($messages, $this->mediaVisionEnabled);
    }

    /**
     * @param  array<string, mixed>  $block
     */
    protected function deserializeStudioContentBlock(array $block): ?ContentBlockInterface
    {
        $storageKey = (string) ($block['storage_key'] ?? '');

        if ($storageKey !== '' && ! array_key_exists('content', $block)) {
            return StoredAttachmentContent::fromStoredArray($block);
        }

        if ($storageKey !== '' && isset($block['content']) && strlen((string) $block['content']) > 512) {
            return StoredAttachmentContent::fromStoredArray($block);
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function serializeMessageContentForStorage(Message $message): array
    {
        return app(ChatContentStorage::class)->serializeForStorage($message->getContentBlocks());
    }
}
