<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:'.((int) config('neuronai-studio.attachments.max_size_kb', 10240)),
            'type' => 'nullable|string|in:image,audio,video,document',
        ]);

        $file = $validated['file'];
        $mimeType = $this->resolveMimeType($file);
        $this->assertAllowedMime($mimeType);

        $type = (string) ($validated['type'] ?? $this->detectType($mimeType));
        $disk = (string) config('neuronai-studio.attachments.disk', 'local');
        $directory = trim((string) config('neuronai-studio.attachments.path', 'neuronai-studio/attachments'), '/');
        $storageKey = $file->store($directory, $disk);

        return response()->json([
            'storage_key' => $storageKey,
            'mime_type' => $mimeType,
            'name' => $file->getClientOriginalName(),
            'type' => $type,
            'url' => route('neuronai-studio.attachments.show', ['storage_key' => $storageKey]),
        ]);
    }

    public function show(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $validated = $request->validate([
            'storage_key' => 'required|string',
        ]);

        $storageKey = (string) $validated['storage_key'];
        $disk = (string) config('neuronai-studio.attachments.disk', 'local');
        $directory = trim((string) config('neuronai-studio.attachments.path', 'neuronai-studio/attachments'), '/');

        if ($directory !== '' && ! str_starts_with($storageKey, $directory.'/')) {
            abort(403);
        }

        if (! Storage::disk($disk)->exists($storageKey)) {
            abort(404);
        }

        return response()->file(
            Storage::disk($disk)->path($storageKey),
            ['Content-Type' => (string) Storage::disk($disk)->mimeType($storageKey)],
        );
    }

    protected function assertAllowedMime(string $mimeType): void
    {
        $allowed = config('neuronai-studio.attachments.allowed_mimes', []);

        if ($allowed === [] || in_array($mimeType, $allowed, true)) {
            return;
        }

        abort(422, "Mime type [{$mimeType}] is not allowed.");
    }

    protected function detectType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        return 'document';
    }

    protected function resolveMimeType(\Illuminate\Http\UploadedFile $file): string
    {
        $mimeType = (string) $file->getMimeType();

        if ($mimeType !== '' && $mimeType !== 'application/octet-stream') {
            return $mimeType;
        }

        $clientMime = (string) $file->getClientMimeType();
        if ($clientMime !== '' && $clientMime !== 'application/octet-stream') {
            return $clientMime;
        }

        return match (strtolower((string) $file->getClientOriginalExtension())) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            default => $mimeType !== '' ? $mimeType : 'application/octet-stream',
        };
    }
}
