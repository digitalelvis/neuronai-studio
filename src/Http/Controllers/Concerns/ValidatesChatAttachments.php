<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Controllers\Concerns;

use ElvisLopesDigital\NeuronAIStudio\Runtime\MessageFactory;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait ValidatesChatAttachments
{
    /** @return array<string, mixed> */
    protected function validateChatPayload(Request $request, bool $requireContent = true): array
    {
        $validated = $request->validate([
            'message' => 'nullable|string',
            'attachments' => 'nullable|array',
            'attachments.*.type' => 'required_with:attachments|string',
            'attachments.*.mime_type' => 'nullable|string',
            'attachments.*.storage_key' => 'required_with:attachments|string',
            'attachments.*.name' => 'nullable|string',
        ]);

        $message = trim((string) ($validated['message'] ?? ''));
        $attachments = is_array($validated['attachments'] ?? null) ? $validated['attachments'] : [];

        if ($requireContent && $message === '' && $attachments === []) {
            throw ValidationException::withMessages([
                'message' => 'A message or at least one attachment is required.',
            ]);
        }

        $attachmentError = app(MessageFactory::class)->validateStoredAttachments($attachments);
        if ($attachmentError !== null) {
            throw ValidationException::withMessages([
                'attachments' => $attachmentError,
            ]);
        }

        $validated['message'] = $message;
        $validated['attachments'] = $attachments;

        return $validated;
    }
}
