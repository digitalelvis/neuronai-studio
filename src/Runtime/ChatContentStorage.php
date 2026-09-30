<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use DigitalElvis\NeuronAIStudio\Runtime\Messages\StoredAttachmentContent;
use NeuronAI\Chat\Messages\ContentBlocks\AudioContent;
use NeuronAI\Chat\Messages\ContentBlocks\ContentBlockInterface;
use NeuronAI\Chat\Messages\ContentBlocks\FileContent;
use NeuronAI\Chat\Messages\ContentBlocks\ImageContent;
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;
use NeuronAI\Chat\Messages\ContentBlocks\VideoContent;
use NeuronAI\Chat\Messages\Message;

class ChatContentStorage
{
    public function __construct(
        protected MessageFactory $messages,
    ) {}

    /**
     * @param  ContentBlockInterface[]  $blocks
     * @return array<int, array<string, mixed>>
     */
    public function serializeForStorage(array $blocks): array
    {
        $serialized = [];

        foreach ($blocks as $block) {
            if ($block instanceof StoredAttachmentContent) {
                $serialized[] = $block->toArray();

                continue;
            }

            $ref = $this->storageReferenceFromMediaBlock($block);
            if ($ref !== null) {
                $serialized[] = $ref;

                continue;
            }

            $serialized[] = $block->toArray();
        }

        return $serialized;
    }

    /**
     * @param  Message[]  $messages
     * @return Message[]
     */
    public function applyVisionPolicy(array $messages, bool $visionEnabled): array
    {
        if ($messages === []) {
            return [];
        }

        return array_map(
            fn (Message $message): Message => $this->applyVisionPolicyToMessage($message, $visionEnabled),
            $messages,
        );
    }

    protected function applyVisionPolicyToMessage(Message $message, bool $visionEnabled): Message
    {
        $blocks = $message->getContentBlocks();
        $resolved = $this->resolveBlocksForInference($blocks, $visionEnabled);

        if ($this->blocksEquivalentForInference($blocks, $resolved)) {
            return $message;
        }

        $clone = clone $message;
        $clone->setContents($resolved === [] ? '' : $resolved);

        return $clone;
    }

    /**
     * @param  ContentBlockInterface[]  $original
     * @param  ContentBlockInterface[]  $resolved
     */
    protected function blocksEquivalentForInference(array $original, array $resolved): bool
    {
        if (count($original) !== count($resolved)) {
            return false;
        }

        foreach ($original as $index => $block) {
            if ($block instanceof StoredAttachmentContent) {
                return false;
            }

            if ($resolved[$index]::class !== $block::class) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  ContentBlockInterface[]  $blocks
     * @return ContentBlockInterface[]
     */
    public function resolveBlocksForInference(array $blocks, bool $visionEnabled): array
    {
        $resolved = [];

        foreach ($blocks as $block) {
            if ($block instanceof StoredAttachmentContent) {
                if (! $visionEnabled) {
                    continue;
                }

                $hydrated = $this->messages->attachmentFromStorageReference([
                    'type' => $block->attachmentType,
                    'storage_key' => $block->storageKey,
                    'mime_type' => $block->mediaType,
                    'name' => $block->name ?? basename($block->storageKey),
                ]);

                if ($hydrated !== null) {
                    $resolved[] = $hydrated;
                }

                continue;
            }

            if (! $visionEnabled && $this->isBinaryMediaBlock($block)) {
                continue;
            }

            $resolved[] = $block;
        }

        return $resolved;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function storageReferenceFromMediaBlock(ContentBlockInterface $block): ?array
    {
        if (! $this->isBinaryMediaBlock($block)) {
            return null;
        }

        $meta = method_exists($block, 'getMetadata') ? $block : null;
        $storageKey = $meta !== null ? (string) ($block->getMetadata('storage_key') ?? '') : '';

        if ($storageKey === '') {
            return null;
        }

        return array_filter([
            'type' => $block->getType()->value,
            'attachment_type' => (string) ($block->getMetadata('attachment_type') ?? $this->attachmentTypeFromBlock($block)),
            'storage_key' => $storageKey,
            'media_type' => $block instanceof ImageContent || $block instanceof AudioContent || $block instanceof VideoContent || $block instanceof FileContent
                ? ($block->mediaType ?? null)
                : null,
            'name' => ($block->getMetadata('attachment_name') ?? null) !== null
                ? (string) $block->getMetadata('attachment_name')
                : null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    protected function isBinaryMediaBlock(ContentBlockInterface $block): bool
    {
        return $block instanceof ImageContent
            || $block instanceof AudioContent
            || $block instanceof VideoContent
            || $block instanceof FileContent;
    }

    protected function attachmentTypeFromBlock(ContentBlockInterface $block): string
    {
        return match (true) {
            $block instanceof ImageContent => 'image',
            $block instanceof AudioContent => 'audio',
            $block instanceof VideoContent => 'video',
            default => 'document',
        };
    }
}
