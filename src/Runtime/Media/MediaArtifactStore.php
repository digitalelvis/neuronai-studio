<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Media;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use NeuronAI\Chat\Messages\ContentBlocks\AudioContent;
use NeuronAI\Chat\Messages\ContentBlocks\ImageContent;
use NeuronAI\Chat\Messages\ContentBlocks\VideoContent;

class MediaArtifactStore
{
    /**
     * @return array{storage_key: string, mime_type: string, name: string, type: string, url: ?string}
     */
    public function store(ImageContent|AudioContent|VideoContent $block, string $type): array
    {
        $mime = $block->mediaType ?: $this->defaultMime($type);
        $extension = $this->extensionFor($mime, $type);
        $disk = (string) config('neuronai-studio.attachments.disk', 'local');
        $directory = trim((string) config('neuronai-studio.attachments.path', 'neuronai-studio/attachments'), '/');
        $name = $type.'-'.Str::lower(Str::random(8)).'.'.$extension;
        $storageKey = ($directory !== '' ? $directory.'/' : '').$name;

        Storage::disk($disk)->put($storageKey, base64_decode($block->content, true) ?: $block->content);

        $url = null;
        try {
            $url = route('neuronai-studio.attachments.show', ['storage_key' => $storageKey]);
        } catch (\Throwable) {
            $url = null;
        }

        return [
            'storage_key' => $storageKey,
            'mime_type' => $mime,
            'name' => $name,
            'type' => $type,
            'url' => $url,
        ];
    }

    protected function defaultMime(string $type): string
    {
        return match ($type) {
            'audio' => 'audio/mpeg',
            'video' => 'video/mp4',
            default => 'image/png',
        };
    }

    protected function extensionFor(string $mime, string $type): string
    {
        return match (true) {
            str_contains($mime, 'jpeg') => 'jpg',
            str_contains($mime, 'webp') => 'webp',
            str_contains($mime, 'wav') => 'wav',
            str_contains($mime, 'mpeg'), str_contains($mime, 'mp3') => 'mp3',
            str_contains($mime, 'ogg') => 'ogg',
            str_contains($mime, 'webm') => 'webm',
            str_contains($mime, 'mp4') => 'mp4',
            $type === 'audio' => 'mp3',
            $type === 'video' => 'mp4',
            default => 'png',
        };
    }
}
