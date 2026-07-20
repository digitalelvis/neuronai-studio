<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Memory;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioTrace;
use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\History\TokenCounter;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Exceptions\ChatHistoryException;

/**
 * Shared trim + optional compaction for Studio chat histories.
 * Default mode never deletes durable rows; summarization replaces the trimmed
 * prefix with a single summary message when the summarizer succeeds.
 */
trait NonDestructiveHistoryTrim
{
    /** @var array<string, mixed>|null */
    protected ?array $compactionMeta = null;

    protected ?HistorySummarizer $summarizer = null;

    /** @var array{provider?: string, model?: string} */
    protected array $agentFallback = [];

    protected ?StudioRun $compactionRun = null;

    protected ?StudioTrace $compactionTrace = null;

    protected bool $storageRewritten = false;

    /**
     * @param  array{provider: string, model: string}  $agentFallback
     */
    public function enableCompaction(
        HistorySummarizer $summarizer,
        array $agentFallback,
        ?StudioRun $run = null,
        ?StudioTrace $trace = null,
    ): static {
        $this->summarizer = $summarizer;
        $this->agentFallback = $agentFallback;
        $this->compactionRun = $run;
        $this->compactionTrace = $trace;

        return $this;
    }

    public function tookStorageRewrite(): bool
    {
        $flag = $this->storageRewritten;
        $this->storageRewritten = false;

        return $flag;
    }

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

    public static function isSummaryMessage(Message $message): bool
    {
        $meta = $message->jsonSerialize();
        if (($meta['studio_kind'] ?? null) === SummaryMessage::KIND) {
            return true;
        }

        $content = (string) ($message->getContent() ?? '');

        return str_starts_with($content, SummaryMessage::PREFIX);
    }

    protected function onTrimHistory(int $index): void
    {
        // Intentionally empty — non-destructive path keeps durable rows.
    }

    protected function trimHistory(): void
    {
        $before = $this->history;
        $beforeCount = count($before);
        $trimmed = $this->trimForPrompt($before, $this->contextWindow);
        $skipIndex = $beforeCount - count($trimmed);
        $tokensAfter = $this->trimmer->getTotalTokens();

        if ($skipIndex > 0) {
            if ($this->shouldCompact()) {
                $this->compactPrefix(array_slice($before, 0, $skipIndex), array_slice($before, $skipIndex), $skipIndex, $tokensAfter);

                return;
            }

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
            // Neuron may leave history intact (trim index 0) while still over budget.
            // With summarization on, compact everything except the latest message.
            if ($this->shouldCompact() && $beforeCount > 1) {
                $this->compactPrefix(
                    array_slice($before, 0, $beforeCount - 1),
                    [$before[$beforeCount - 1]],
                    $beforeCount - 1,
                    $tokensAfter,
                );

                return;
            }

            $latest = $before[$beforeCount - 1];
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
     * @param  Message[]  $prefix
     * @param  Message[]  $suffix
     */
    protected function compactPrefix(array $prefix, array $suffix, int $skipIndex, int $tokensAfter): void
    {
        $result = $this->summarizer->summarize(
            $prefix,
            $this->agentFallback,
            $this->compactionRun,
            $this->compactionTrace,
        );

        if (! $result->ok) {
            $this->history = array_values($suffix);
            $this->onTrimHistory($skipIndex);
            $this->compactionMeta = [
                'mode' => 'non_destructive',
                'trimmed_count' => $skipIndex,
                'tokens_after' => $tokensAfter,
                'over_budget_single' => false,
                'summarization_enabled' => true,
                'summarizer_fallback' => 'trim',
                'summarizer_error' => $result->error,
            ];

            return;
        }

        $summary = $this->makeSummaryMessage($result->summary);
        $summary = $this->truncateSummaryToWindow($summary);
        $this->history = array_values(array_merge([$summary], $suffix));
        $this->persistCompactedHistory($this->history);
        $this->compactionMeta = [
            'mode' => 'compaction',
            'trimmed_count' => $skipIndex,
            'tokens_after' => $tokensAfter,
            'over_budget_single' => false,
            'summarization_enabled' => true,
            'summarizer_source' => $result->source,
            'messages_summarized' => count($prefix),
        ];
    }

    protected function makeSummaryMessage(string $summary): Message
    {
        $message = new Message(
            MessageRole::SYSTEM,
            SummaryMessage::PREFIX."\n".$summary,
        );
        $message->addMetadata('studio_kind', SummaryMessage::KIND);

        return $message;
    }

    protected function truncateSummaryToWindow(Message $summary): Message
    {
        $counter = new TokenCounter;
        if ($counter->count($summary) <= $this->contextWindow) {
            return $summary;
        }

        $body = (string) ($summary->getContent() ?? '');
        $prefix = SummaryMessage::PREFIX."\n";
        $raw = str_starts_with($body, $prefix) ? substr($body, strlen($prefix)) : $body;
        $budgetChars = max(32, (int) (($this->contextWindow - 8) * 4) - strlen($prefix));
        $truncated = mb_substr($raw, 0, $budgetChars).'…';

        return $this->makeSummaryMessage($truncated);
    }

    /**
     * @param  Message[]  $messages
     */
    protected function persistCompactedHistory(array $messages): void
    {
        // In-memory: nothing to persist.
    }

    protected function shouldCompact(): bool
    {
        return $this->summarizationEnabled() && $this->summarizer !== null;
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
