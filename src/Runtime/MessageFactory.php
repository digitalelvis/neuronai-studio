<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime;

use ElvisLopesDigital\NeuronAIStudio\Runtime\Exceptions\HumanInputRequiredException;
use Illuminate\Support\Facades\Storage;
use NeuronAI\Chat\Enums\SourceType;
use NeuronAI\Chat\Messages\ContentBlocks\AudioContent;
use NeuronAI\Chat\Messages\ContentBlocks\FileContent;
use NeuronAI\Chat\Messages\ContentBlocks\ImageContent;
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;
use NeuronAI\Chat\Messages\ContentBlocks\VideoContent;
use NeuronAI\Chat\Messages\UserMessage;

class MessageFactory
{
    public const ATTACHMENT_ONLY_PROMPT = 'Analyze the attached file(s).';

    /** @param  array<int, array<string, mixed>>  $attachments */
    public function validateStoredAttachments(array $attachments): ?string
    {
        if ($attachments === []) {
            return null;
        }

        $disk = (string) config('neuronai-studio.attachments.disk', 'local');

        foreach ($attachments as $attachment) {
            $storageKey = (string) ($attachment['storage_key'] ?? '');
            if ($storageKey === '') {
                return 'Each attachment must include a storage_key.';
            }

            if (! Storage::disk($disk)->exists($storageKey)) {
                $name = (string) ($attachment['name'] ?? basename($storageKey));

                return "Uploaded file not found for attachment [{$name}]. Please upload again.";
            }
        }

        return null;
    }

    /** @param  array<int, array<string, mixed>>  $attachments */
    public function resolveMessageWithAttachments(string $message, array $attachments = []): UserMessage
    {
        if ($message === '' && $attachments !== []) {
            $message = self::ATTACHMENT_ONLY_PROMPT;
        }

        return $this->userMessage($message, $attachments);
    }

    /** @param  array<int, array<string, mixed>>  $attachments */
    public function userMessage(string $message, array $attachments = []): UserMessage
    {
        if ($attachments === []) {
            return new UserMessage($message);
        }

        $blocks = [];

        if ($message !== '') {
            $blocks[] = new TextContent($message);
        }

        foreach ($attachments as $attachment) {
            $block = $this->attachmentBlock($attachment);
            if ($block === null) {
                $name = (string) ($attachment['name'] ?? $attachment['storage_key'] ?? 'attachment');

                throw new \RuntimeException("Unable to read uploaded attachment [{$name}].");
            }

            $blocks[] = $block;
        }

        if ($blocks === []) {
            return new UserMessage('');
        }

        return new UserMessage($blocks);
    }

    /** @param  array<string, mixed>  $attachment */
    protected function attachmentBlock(array $attachment): ImageContent|AudioContent|VideoContent|FileContent|null
    {
        $storageKey = (string) ($attachment['storage_key'] ?? '');
        if ($storageKey === '') {
            return null;
        }

        $disk = (string) config('neuronai-studio.attachments.disk', 'local');
        if (! Storage::disk($disk)->exists($storageKey)) {
            return null;
        }

        $mimeType = (string) ($attachment['mime_type'] ?? Storage::disk($disk)->mimeType($storageKey));
        $content = base64_encode(Storage::disk($disk)->get($storageKey));
        $type = (string) ($attachment['type'] ?? 'document');
        $name = (string) ($attachment['name'] ?? basename($storageKey));

        return match ($type) {
            'image' => new ImageContent($content, SourceType::BASE64, $mimeType),
            'audio' => new AudioContent($content, SourceType::BASE64, $mimeType),
            'video' => new VideoContent($content, SourceType::BASE64, $mimeType),
            default => new FileContent($content, SourceType::BASE64, $mimeType, $name),
        };
    }
}
