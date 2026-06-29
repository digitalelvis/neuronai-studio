<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:'.((int) config('neuronai-studio.attachments.max_size_kb', 10240)),
            'type' => 'nullable|string|in:image,audio,video,document',
        ]);

        $file = $validated['file'];
        $mimeType = (string) $file->getMimeType();
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
            'url' => Storage::disk($disk)->url($storageKey),
        ]);
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
}
