<?php

namespace DigitalElvis\NeuronAIStudio\Services;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioChatMessage;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Support\ChatThreadKey;
use Illuminate\Support\Collection;

class ChatThreadIndex
{
    /**
     * @return list<array{id: string, updated_at: string|null, preview: string|null, message_count: int, run_count: int, label: string}>
     */
    public function listForAgent(int $agentId): array
    {
        return $this->listForEntity(AgentDefinition::class, $agentId, fn (string $publicId) => [
            ChatThreadKey::forAgent($agentId, $publicId),
            $publicId,
        ]);
    }

    /**
     * @return list<array{id: string, updated_at: string|null, preview: string|null, message_count: int, run_count: int, label: string}>
     */
    public function listForWorkflow(int $workflowId): array
    {
        return $this->listForEntity(WorkflowDefinition::class, $workflowId, fn (string $publicId) => [
            ChatThreadKey::forWorkflow($workflowId, $publicId),
            $publicId,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function runsForAgentThread(int $agentId, string $threadId): array
    {
        if (str_contains($threadId, ':')) {
            $threadId = ChatThreadKey::publicId($threadId);
        }

        $thread = StudioThread::query()
            ->where('id', $threadId)
            ->where('entity_type', AgentDefinition::class)
            ->where('entity_id', $agentId)
            ->first();

        if ($thread === null) {
            return [];
        }

        return $thread->runs()
            ->latest('started_at')
            ->get()
            ->map(fn (StudioRun $run) => $this->runSummary($run))
            ->values()
            ->all();
    }

    /**
     * @param  callable(string): list<string>  $messageKeys
     * @return list<array{id: string, updated_at: string|null, preview: string|null, message_count: int, run_count: int, label: string}>
     */
    protected function listForEntity(string $entityType, int $entityId, callable $messageKeys): array
    {
        $threads = StudioThread::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->withCount('runs')
            ->withMax('runs', 'started_at')
            ->orderByDesc('runs_max_started_at')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        if ($threads->isEmpty()) {
            return [];
        }

        $previews = $this->firstUserPreviews($threads, $messageKeys);

        return $threads->map(function (StudioThread $thread) use ($previews) {
            $preview = $previews[$thread->id] ?? null;
            $updatedAt = $thread->runs_max_started_at
                ?? $thread->updated_at?->toIso8601String();

            if ($updatedAt instanceof \DateTimeInterface) {
                $updatedAt = $updatedAt->format(\DateTimeInterface::ATOM);
            }

            return [
                'id' => $thread->id,
                'updated_at' => $updatedAt,
                'preview' => $preview,
                'message_count' => $preview !== null ? 1 : 0,
                'run_count' => (int) $thread->runs_count,
                'label' => $this->labelFor($preview, $updatedAt, $thread->id),
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, StudioThread>  $threads
     * @param  callable(string): list<string>  $messageKeys
     * @return array<string, string>
     */
    protected function firstUserPreviews(Collection $threads, callable $messageKeys): array
    {
        $keysByPublicId = [];
        $allKeys = [];

        foreach ($threads as $thread) {
            $keys = $messageKeys($thread->id);
            $keysByPublicId[$thread->id] = $keys;
            array_push($allKeys, ...$keys);
        }

        if ($allKeys === []) {
            return [];
        }

        $messages = StudioChatMessage::query()
            ->whereIn('thread_id', array_unique($allKeys))
            ->where('role', 'user')
            ->orderBy('id')
            ->get(['thread_id', 'content']);

        $previews = [];

        foreach ($messages as $message) {
            $publicId = ChatThreadKey::publicId((string) $message->thread_id);
            if (isset($previews[$publicId])) {
                continue;
            }

            $text = $this->textFromContent($message->content);
            if ($text === '') {
                continue;
            }

            $previews[$publicId] = mb_substr($text, 0, 80);
        }

        return $previews;
    }

    protected function labelFor(?string $preview, ?string $updatedAt, string $threadId): string
    {
        if ($preview !== null && $preview !== '') {
            return $preview;
        }

        if ($updatedAt !== null) {
            try {
                $date = new \DateTimeImmutable($updatedAt);

                return 'Session '.$date->format('M j, H:i:s');
            } catch (\Exception) {
            }
        }

        return 'Session '.mb_substr($threadId, 0, 8);
    }

    protected function textFromContent(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
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

            if (isset($block['content']) && is_string($block['content'])) {
                $parts[] = $block['content'];

                continue;
            }

            if (isset($block['text']) && is_string($block['text'])) {
                $parts[] = $block['text'];
            }
        }

        return implode('', $parts);
    }

    /** @return array<string, mixed> */
    protected function runSummary(StudioRun $run): array
    {
        $inputPreview = null;
        if (is_array($run->input)) {
            $inputPreview = $run->input['message']
                ?? $run->input['input']
                ?? (count($run->input) ? json_encode($run->input) : null);
        }

        return [
            'id' => $run->id,
            'status' => $run->status,
            'awaiting_node_id' => $run->awaitingNodeId(),
            'input_preview' => is_string($inputPreview) ? mb_substr($inputPreview, 0, 120) : null,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'duration_ms' => $run->durationMs(),
            'prompt_tokens' => $run->prompt_tokens,
            'completion_tokens' => $run->completion_tokens,
            'total_tokens' => $run->total_tokens,
            'estimated_cost' => $run->estimated_cost ?? '0.000000',
            'currency' => config('neuronai-studio.usage.currency', 'USD'),
        ];
    }
}
