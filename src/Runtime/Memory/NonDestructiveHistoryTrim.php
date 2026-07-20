<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Memory;

use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Exceptions\ChatHistoryException;

/**
 * Shared non-destructive trim behavior for Studio chat histories.
 * Trims the in-memory prompt path but never deletes persisted rows.
 */
trait NonDestructiveHistoryTrim
{
    /** @var array<string, mixed>|null */
    protected ?array $compactionMeta = null;

    /**
     * @return array<string, mixed>|null
     */
    public function pullCompactionMeta(): ?array
    {
        $meta = $this->compactionMeta;
        $this->compactionMeta = null;

        return $meta;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function compactionMeta(): ?array
    {
        return $this->compactionMeta;
    }

    protected function onTrimHistory(int $index): void
    {
        // Intentionally empty — Studio keeps durable rows (Eloquent) / full session (in-memory).
    }

    protected function trimHistory(): void
    {
        $beforeCount = count($this->history);
        $trimmed = $this->trimForPrompt($this->history, $this->contextWindow);
        $afterCount = count($trimmed);
        $skipIndex = $beforeCount - $afterCount;
        $tokensAfter = $this->trimmer->getTotalTokens();

        if ($skipIndex > 0) {
            $this->history = $trimmed;
            $this->onTrimHistory($skipIndex);
            $this->compactionMeta = [
                'mode' => 'non_destructive',
                'trimmed_count' => $skipIndex,
                'tokens_after' => $tokensAfter,
                'over_budget_single' => false,
                'summarization_enabled' => $this->summarizationEnabled(),
            ];

            return;
        }

        if ($tokensAfter > $this->contextWindow && $beforeCount >= 1) {
            $latest = $this->history[$beforeCount - 1];
            $trimmedCount = $beforeCount - 1;
            $this->history = [$latest];
            if ($trimmedCount > 0) {
                $this->onTrimHistory($trimmedCount);
            }
            $this->compactionMeta = [
                'mode' => 'non_destructive',
                'trimmed_count' => $trimmedCount,
                'tokens_after' => $tokensAfter,
                'over_budget_single' => true,
                'summarization_enabled' => $this->summarizationEnabled(),
            ];
        }
    }

    /**
     * @param  Message[]  $messages
     * @return Message[]
     */
    protected function trimForPrompt(array $messages, int $contextWindow): array
    {
        try {
            return $this->trimmer->trim($messages, $contextWindow);
        } catch (ChatHistoryException) {
            return $this->safeUserSuffix($messages);
        }
    }

    /**
     * Keep the latest user turn and everything after it (never empty if messages exist).
     *
     * @param  Message[]  $messages
     * @return Message[]
     */
    protected function safeUserSuffix(array $messages): array
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if ($messages[$i] instanceof UserMessage) {
                return array_values(array_slice($messages, $i));
            }
        }

        return $messages === [] ? [] : [array_values($messages)[count($messages) - 1]];
    }

    protected function summarizationEnabled(): bool
    {
        return false;
    }
}
