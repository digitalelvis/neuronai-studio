<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers\Concerns;

use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

trait ValidatesChatAttachments
{
    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @return array<string, mixed>
     */
    protected function validateStreamRequest(Request $request, array $rules, array $messages = []): array
    {
        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            $this->throwStreamValidationError(
                $validator->errors()->first() ?: 'The given data was invalid.',
                $validator->errors()->toArray(),
            );
        }

        return $validator->validated();
    }

    /** @return array<string, mixed> */
    protected function validateChatPayload(Request $request, bool $requireContent = true): array
    {
        $validated = $this->validateStreamRequest($request, [
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
            $this->throwStreamValidationError(
                'A message or at least one attachment is required.',
                ['message' => ['A message or at least one attachment is required.']],
            );
        }

        $attachmentError = app(MessageFactory::class)->validateStoredAttachments($attachments);
        if ($attachmentError !== null) {
            $this->throwStreamValidationError($attachmentError, ['attachments' => [$attachmentError]]);
        }

        $validated['message'] = $message;
        $validated['attachments'] = $attachments;

        return $validated;
    }

    /** @param  array<string, array<int, string>>  $errors */
    protected function throwStreamValidationError(string $message, array $errors): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
            'errors' => $errors,
        ], 422));
    }
}
