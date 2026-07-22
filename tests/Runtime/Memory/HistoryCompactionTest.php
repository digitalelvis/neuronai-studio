<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime\Memory;

use DigitalElvis\NeuronAIStudio\Models\StudioChatMessage;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\Memory\HistorySummarizer;
use DigitalElvis\NeuronAIStudio\Runtime\Memory\StudioEloquentChatHistory;
use DigitalElvis\NeuronAIStudio\Runtime\Memory\SummaryMessage;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Testing\FakeAIProvider;
use RuntimeException;

class HistoryCompactionTest extends TestCase
{
    public function test_compaction_persists_summary_and_replaces_prefix(): void
    {
        $threadId = 'compact-'.uniqid();
        $history = $this->historyWithSummarizer(
            $threadId,
            new FakeAIProvider(new AssistantMessage('Earlier turns about cats and dogs.')),
        );

        $this->seedOverBudgetPairs($history);

        $rows = StudioChatMessage::query()->where('thread_id', $threadId)->orderBy('id')->get();
        $this->assertGreaterThanOrEqual(1, $rows->count());

        $summaryRows = $rows->filter(fn ($row) => ($row->meta['studio_kind'] ?? null) === SummaryMessage::KIND);
        $this->assertCount(1, $summaryRows);
        $this->assertSame($rows->first()->id, $summaryRows->first()->id);

        $prompt = $history->getMessages();
        $this->assertTrue(StudioEloquentChatHistory::isSummaryMessage($prompt[0]));
        $this->assertLessThan(4, count($prompt));

        $meta = $history->compactionMeta();
        $this->assertSame('compaction', $meta['mode']);
        $this->assertNotEmpty($meta['summarizer_source']);
    }

    public function test_roll_forward_keeps_single_active_summary(): void
    {
        $threadId = 'roll-'.uniqid();
        $provider = new FakeAIProvider(
            new AssistantMessage('Summary one.'),
            new AssistantMessage('Summary two rolled forward.'),
        );
        $history = $this->historyWithSummarizer($threadId, $provider, window: 100);

        $this->seedOverBudgetPairs($history);

        $this->assertSame(
            1,
            StudioChatMessage::query()
                ->where('thread_id', $threadId)
                ->get()
                ->filter(fn ($row) => ($row->meta['studio_kind'] ?? null) === SummaryMessage::KIND)
                ->count(),
        );

        $history->addMessage(new UserMessage($this->pad('u3', 70)));
        $history->addMessage(new AssistantMessage($this->pad('a3', 70)));
        $history->addMessage(new UserMessage($this->pad('u4', 70)));
        $history->addMessage(new AssistantMessage($this->pad('a4', 70)));

        $summaries = StudioChatMessage::query()
            ->where('thread_id', $threadId)
            ->orderBy('id')
            ->get()
            ->filter(fn ($row) => ($row->meta['studio_kind'] ?? null) === SummaryMessage::KIND);

        $this->assertCount(1, $summaries);
        $encoded = json_encode($summaries->first()->content);
        $this->assertStringContainsString('rolled forward', (string) $encoded);
    }

    public function test_summarizer_failure_falls_back_to_non_destructive_trim(): void
    {
        $threadId = 'fallback-'.uniqid();

        $failing = $this->createMock(AIProviderInterface::class);
        $failing->method('chat')->willThrowException(new RuntimeException('down'));

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($failing);

        config([
            'neuronai-studio.memory.summarizer.provider' => null,
            'neuronai-studio.memory.summarizer.model' => null,
        ]);

        $history = new StudioEloquentChatHistory(
            threadId: $threadId,
            modelClass: StudioChatMessage::class,
            contextWindow: 120,
            summarization: true,
        );
        $history->enableCompaction(
            new HistorySummarizer($registry),
            ['provider' => 'openai', 'model' => 'gpt-4o-mini'],
        );

        $this->seedOverBudgetPairs($history);

        $this->assertSame(4, StudioChatMessage::query()->where('thread_id', $threadId)->count());
        $this->assertLessThan(4, count($history->getMessages()));

        $meta = $history->compactionMeta();
        $this->assertSame('non_destructive', $meta['mode']);
        $this->assertSame('trim', $meta['summarizer_fallback'] ?? null);
        $this->assertSame(
            0,
            StudioChatMessage::query()
                ->where('thread_id', $threadId)
                ->get()
                ->filter(fn ($row) => ($row->meta['studio_kind'] ?? null) === SummaryMessage::KIND)
                ->count(),
        );
    }

    public function test_summary_survives_history_reload(): void
    {
        $threadId = 'reload-'.uniqid();
        $history = $this->historyWithSummarizer(
            $threadId,
            new FakeAIProvider(new AssistantMessage('Persisted summary.')),
        );
        $this->seedOverBudgetPairs($history);
        $this->assertSame('compaction', $history->compactionMeta()['mode']);

        $reloaded = new StudioEloquentChatHistory(
            threadId: $threadId,
            modelClass: StudioChatMessage::class,
            contextWindow: 120,
            summarization: true,
        );

        $messages = $reloaded->getMessages();
        $this->assertNotEmpty($messages);
        $this->assertTrue(StudioEloquentChatHistory::isSummaryMessage($messages[0]));
    }

    private function seedOverBudgetPairs(StudioEloquentChatHistory $history): void
    {
        $history->addMessage(new UserMessage($this->pad('old-user', 80)));
        $history->addMessage(new AssistantMessage($this->pad('old-assistant', 80)));
        $history->addMessage(new UserMessage($this->pad('new-user', 80)));
        $history->addMessage(new AssistantMessage($this->pad('new-assistant', 80)));
    }

    private function historyWithSummarizer(
        string $threadId,
        AIProviderInterface $provider,
        int $window = 120,
    ): StudioEloquentChatHistory {
        config([
            'neuronai-studio.memory.summarizer.provider' => 'openai',
            'neuronai-studio.memory.summarizer.model' => 'gpt-4o-mini',
        ]);

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($provider);

        $history = new StudioEloquentChatHistory(
            threadId: $threadId,
            modelClass: StudioChatMessage::class,
            contextWindow: $window,
            summarization: true,
        );
        $history->enableCompaction(
            new HistorySummarizer($registry),
            ['provider' => 'openai', 'model' => 'gpt-4o-mini'],
        );

        return $history;
    }

    private function pad(string $label, int $chars): string
    {
        return $label.' '.str_repeat('x', max(0, $chars - strlen($label) - 1));
    }
}
