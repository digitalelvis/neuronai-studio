<?php

namespace DigitalElvis\NeuronAIStudio\Integration;

use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Normalizes AG-UI {@see RunAgentInput} and Studio `{ message, thread_id }`
 * bodies for integrate `agui` routes.
 */
final class RunAgentInputParser
{
    public function __construct(
        protected MessageFactory $messages,
    ) {}

    /**
     * @return array{
     *     thread_id: string,
     *     run_id: string,
     *     message: string,
     *     state: array<string, mixed>,
     *     resume: list<array<string, mixed>>,
     *     attachments: array<int, array<string, mixed>>,
     *     parameters: array<string, mixed>
     * }
     */
    public function parse(Request $request, bool $requireContent = true): array
    {
        $resume = $this->resumeItems($request);

        if ($this->isCancelledResume($resume)) {
            $this->fail('Cancelled resume is not supported.', [
                'resume' => ['Cancelled resume is not supported.'],
            ]);
        }

        $threadId = $this->stringId($request->input('threadId') ?? $request->input('thread_id'))
            ?? (string) Str::uuid();
        $runId = $this->stringId($request->input('runId') ?? $request->input('run_id'))
            ?? (string) Str::uuid();

        $message = $this->lastUserMessage($request);
        if ($message === null) {
            $message = trim((string) $request->input('message', ''));
        }

        $attachments = is_array($request->input('attachments')) ? $request->input('attachments') : [];

        $hasResume = $resume !== [];

        if ($requireContent && ! $hasResume && $message === '' && $attachments === []) {
            $this->fail(
                'A message or at least one attachment is required.',
                ['message' => ['A message or at least one attachment is required.']],
            );
        }

        $attachmentError = $this->messages->validateStoredAttachments($attachments);
        if ($attachmentError !== null) {
            $this->fail($attachmentError, ['attachments' => [$attachmentError]]);
        }

        $parameters = is_array($request->input('parameters')) ? $request->input('parameters') : [];

        return [
            'thread_id' => $threadId,
            'run_id' => $runId,
            'message' => $message,
            'state' => $this->workflowState($request),
            'resume' => $resume,
            'attachments' => $attachments,
            'parameters' => $parameters,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function resumeItems(Request $request): array
    {
        $resume = $request->input('resume');

        if (! is_array($resume) || $resume === []) {
            return [];
        }

        if ($this->isAssoc($resume)) {
            return [$resume];
        }

        $items = [];

        foreach ($resume as $item) {
            if (is_array($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $resume
     */
    public function resumeMessage(array $resume): string
    {
        $payload = $resume[0]['payload'] ?? null;

        if (is_string($payload)) {
            return trim($payload);
        }

        if (! is_array($payload)) {
            return '';
        }

        foreach (['message', 'input', 'text'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key]) && trim($payload[$key]) !== '') {
                return trim($payload[$key]);
            }
        }

        return '';
    }

    /**
     * @param  list<array<string, mixed>>  $resume
     */
    public function resumeApproval(array $resume): ?string
    {
        $payload = $resume[0]['payload'] ?? null;

        if (! is_array($payload)) {
            return null;
        }

        $approval = $payload['approval'] ?? null;

        return in_array($approval, ['approve', 'reject'], true) ? $approval : null;
    }

    public function interruptId(array $resume): string
    {
        return trim((string) ($resume[0]['interruptId'] ?? ''));
    }

    protected function lastUserMessage(Request $request): ?string
    {
        $messages = $request->input('messages');

        if (! is_array($messages) || $messages === []) {
            return null;
        }

        for ($i = count($messages) - 1; $i >= 0; $i--) {
            $item = $messages[$i];

            if (! is_array($item)) {
                continue;
            }

            if (($item['role'] ?? null) !== 'user') {
                continue;
            }

            $text = $this->messageContent($item['content'] ?? null);

            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    protected function messageContent(mixed $content): string
    {
        if (is_string($content)) {
            return trim($content);
        }

        if (! is_array($content)) {
            return '';
        }

        $parts = [];

        foreach ($content as $block) {
            if (is_string($block)) {
                $parts[] = $block;

                continue;
            }

            if (! is_array($block)) {
                continue;
            }

            if (isset($block['text']) && is_string($block['text'])) {
                $parts[] = $block['text'];

                continue;
            }

            if (isset($block['content']) && is_string($block['content'])) {
                $parts[] = $block['content'];
            }
        }

        return trim(implode('', $parts));
    }

    /**
     * @return array<string, mixed>
     */
    protected function workflowState(Request $request): array
    {
        $state = $request->input('state');

        if (is_array($state) && $this->isAssoc($state)) {
            return $state;
        }

        $context = $request->input('context');

        if (is_array($context) && $this->isAssoc($context)) {
            return $context;
        }

        return [];
    }

    /**
     * @param  list<array<string, mixed>>  $resume
     */
    protected function isCancelledResume(array $resume): bool
    {
        if ($resume === []) {
            return false;
        }

        return ($resume[0]['status'] ?? null) === 'cancelled';
    }

    protected function stringId(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || strlen($value) > 191) {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $value
     */
    protected function isAssoc(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    protected function fail(string $message, array $errors): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
            'errors' => $errors,
        ], 422));
    }
}
