<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Messages;

use NeuronAI\Chat\Enums\ContentBlockType;
use NeuronAI\Chat\Messages\ContentBlocks\ContentBlockInterface;

/**
 * Persisted chat reference to an attachment on the Studio storage disk.
 * Hydrated to binary content only when vision is enabled for inference.
 */
class StoredAttachmentContent implements ContentBlockInterface
{
    public function __construct(
        public readonly string $storageKey,
        public readonly string $attachmentType,
        public readonly ?string $mediaType = null,
        public readonly ?string $name = null,
    ) {}

    /**
     * @param  array<string, mixed>  $block
     */
    public static function fromStoredArray(array $block): self
    {
        $type = (string) ($block['attachment_type'] ?? $block['type'] ?? 'document');

        return new self(
            storageKey: (string) ($block['storage_key'] ?? ''),
            attachmentType: $type,
            mediaType: isset($block['media_type']) ? (string) $block['media_type'] : null,
            name: isset($block['name']) ? (string) $block['name'] : null,
        );
    }

    public function getType(): ContentBlockType
    {
        return match ($this->attachmentType) {
            'image' => ContentBlockType::IMAGE,
            'audio' => ContentBlockType::AUDIO,
            'video' => ContentBlockType::VIDEO,
            default => ContentBlockType::FILE,
        };
    }

    public function getContent(): string
    {
        return '';
    }

    public function accumulateContent(string $content): void
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'type' => $this->getType()->value,
            'attachment_type' => $this->attachmentType,
            'storage_key' => $this->storageKey,
            'media_type' => $this->mediaType,
            'name' => $this->name,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
