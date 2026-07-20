<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Memory;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioTrace;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Usage\UsageRecorder;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\AIProviderInterface;
use Throwable;

/**
 * Compacts trimmed history prefixes via a dedicated cheap model when configured,
 * falling back to the agent's provider/model. Failures return SummarizeResult::failure
 * (never throw) so callers can degrade to non-destructive trim.
 */
class HistorySummarizer
{
    private const PROMPT = <<<'PROMPT'
Summarize the following conversation turns into a concise paragraph that preserves
facts, decisions, user preferences, and open questions. Do not invent details.
Write in the same language as the conversation.

Conversation:
%s
PROMPT;

    public function __construct(
        protected ProviderRegistry $providers,
        protected UsageRecorder $usageRecorder = new UsageRecorder,
    ) {}

    /**
     * @param  list<string|Message>  $messages
     * @param  array{provider: string, model: string}  $agentFallback
     */
    public function summarize(
        array $messages,
        array $agentFallback,
        ?StudioRun $run = null,
        ?StudioTrace $trace = null,
    ): SummarizeResult {
        $transcript = $this->formatTranscript($messages);
        if ($transcript === '') {
            return SummarizeResult::failure('No messages to summarize.');
        }

        $dedicated = $this->dedicatedTarget();
        if ($dedicated !== null) {
            $result = $this->attempt(
                $dedicated['provider'],
                $dedicated['model'],
                $transcript,
                SummarizeResult::SOURCE_DEDICATED,
                $run,
                $trace,
            );
            if ($result->ok) {
                return $result;
            }
        }

        $agentProvider = (string) ($agentFallback['provider'] ?? '');
        $agentModel = (string) ($agentFallback['model'] ?? '');
        if ($agentProvider === '' || $agentModel === '') {
            return SummarizeResult::failure(
                $dedicated !== null
                    ? 'Dedicated summarizer failed and agent fallback is incomplete.'
                    : 'Summarizer provider/model not configured and agent fallback is incomplete.',
            );
        }

        return $this->attempt(
            $agentProvider,
            $agentModel,
            $transcript,
            SummarizeResult::SOURCE_AGENT,
            $run,
            $trace,
        );
    }

    /**
     * @return array{provider: string, model: string}|null
     */
    public function dedicatedTarget(): ?array
    {
        $provider = config('neuronai-studio.memory.summarizer.provider');
        $model = config('neuronai-studio.memory.summarizer.model');

        if (! is_string($provider) || $provider === '' || ! is_string($model) || $model === '') {
            return null;
        }

        return ['provider' => $provider, 'model' => $model];
    }

    /**
     * @param  list<string|Message>  $messages
     */
    protected function formatTranscript(array $messages): string
    {
        $lines = [];
        foreach ($messages as $message) {
            if ($message instanceof Message) {
                $role = method_exists($message, 'getRole') ? (string) $message->getRole() : 'message';
                $content = (string) ($message->getContent() ?? '');
                if ($content === '') {
                    continue;
                }
                $lines[] = strtoupper($role).': '.$content;

                continue;
            }

            $text = trim((string) $message);
            if ($text !== '') {
                $lines[] = $text;
            }
        }

        return implode("\n\n", $lines);
    }

    protected function attempt(
        string $provider,
        string $model,
        string $transcript,
        string $source,
        ?StudioRun $run,
        ?StudioTrace $trace,
    ): SummarizeResult {
        try {
            $ai = $this->providers->resolve($provider, $model);
            $response = $this->chat($ai, $transcript);
            $summary = trim((string) ($response->getContent() ?? ''));

            if ($summary === '') {
                return SummarizeResult::failure("Summarizer [{$provider}/{$model}] returned an empty summary.");
            }

            $usage = null;
            $tokenUsage = $response->getUsage();
            if ($tokenUsage !== null) {
                $usage = [
                    'prompt_tokens' => (int) $tokenUsage->inputTokens,
                    'completion_tokens' => (int) $tokenUsage->outputTokens,
                ];
            }

            if ($run !== null && $trace !== null) {
                $this->usageRecorder->recordLlmSpan(
                    $run,
                    $trace,
                    $provider,
                    $model,
                    (int) ($usage['prompt_tokens'] ?? 0),
                    (int) ($usage['completion_tokens'] ?? 0),
                    parentRun: $run->parent_run_id !== null
                        ? StudioRun::query()->find($run->parent_run_id)
                        : null,
                    input: ['kind' => 'history_summarization', 'source' => $source],
                    output: ['summary' => $summary],
                );
            }

            return SummarizeResult::success($summary, $source, $provider, $model, $usage);
        } catch (Throwable $e) {
            return SummarizeResult::failure(
                "Summarizer [{$provider}/{$model}] failed: ".$e->getMessage(),
            );
        }
    }

    protected function chat(AIProviderInterface $ai, string $transcript): Message
    {
        $prompt = sprintf(self::PROMPT, $transcript);

        return $ai->chat(new UserMessage($prompt));
    }
}
