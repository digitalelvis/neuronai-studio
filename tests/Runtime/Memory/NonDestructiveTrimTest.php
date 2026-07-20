<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime\Memory;

use DigitalElvis\NeuronAIStudio\Models\StudioChatMessage;
use DigitalElvis\NeuronAIStudio\Runtime\Memory\StudioEloquentChatHistory;
use DigitalElvis\NeuronAIStudio\Runtime\Memory\StudioInMemoryChatHistory;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\UserMessage;

class NonDestructiveTrimTest extends TestCase
{
    public function test_eloquent_trim_excludes_from_prompt_but_keeps_rows(): void
    {
        $threadId = 'trim-thread-'.uniqid();
        $window = 120;

        $history = new StudioEloquentChatHistory(
            threadId: $threadId,
            modelClass: StudioChatMessage::class,
            contextWindow: $window,
            summarization: false,
        );

        // Each pair fits alone; four messages exceed the window.
        $history->addMessage(new UserMessage($this->pad('old-user', 80)));
        $history->addMessage(new AssistantMessage($this->pad('old-assistant', 80)));
        $history->addMessage(new UserMessage($this->pad('new-user', 80)));
        $history->addMessage(new AssistantMessage($this->pad('new-assistant', 80)));

        $dbCount = StudioChatMessage::query()->where('thread_id', $threadId)->count();
        $this->assertSame(4, $dbCount, 'All rows must remain after over-budget trim');

        $promptMessages = $history->getMessages();
        $this->assertLessThan(4, count($promptMessages));
        $this->assertNotEmpty($promptMessages);

        $meta = $history->compactionMeta();
        $this->assertNotNull($meta);
        $this->assertSame('non_destructive', $meta['mode']);
        $this->assertGreaterThan(0, $meta['trimmed_count']);
        $this->assertFalse($meta['summarization_enabled']);
        $this->assertContains($meta['over_budget_single'], [true, false]);
    }

    public function test_in_memory_trim_records_meta_without_losing_session_rows_concept(): void
    {
        $history = new StudioInMemoryChatHistory(contextWindow: 120, summarization: false);

        $history->addMessage(new UserMessage($this->pad('u1', 80)));
        $history->addMessage(new AssistantMessage($this->pad('a1', 80)));
        $history->addMessage(new UserMessage($this->pad('u2', 80)));
        $history->addMessage(new AssistantMessage($this->pad('a2', 80)));

        $this->assertLessThan(4, count($history->getMessages()));
        $this->assertNotNull($history->compactionMeta());
        $this->assertSame('non_destructive', $history->compactionMeta()['mode']);
    }

    public function test_single_message_over_budget_keeps_latest_and_records_condition(): void
    {
        $threadId = 'over-budget-'.uniqid();
        $history = new StudioEloquentChatHistory(
            threadId: $threadId,
            modelClass: StudioChatMessage::class,
            contextWindow: 30,
            summarization: false,
        );

        $history->addMessage(new UserMessage($this->pad('huge', 400)));

        $this->assertSame(1, StudioChatMessage::query()->where('thread_id', $threadId)->count());
        $this->assertCount(1, $history->getMessages());

        $meta = $history->compactionMeta();
        $this->assertNotNull($meta);
        $this->assertTrue($meta['over_budget_single']);
        $this->assertSame('non_destructive', $meta['mode']);
    }

    public function test_under_budget_leaves_all_messages_and_no_meta(): void
    {
        $threadId = 'under-budget-'.uniqid();
        $history = new StudioEloquentChatHistory(
            threadId: $threadId,
            modelClass: StudioChatMessage::class,
            contextWindow: 50000,
        );

        $history->addMessage(new UserMessage('hi'));
        $history->addMessage(new AssistantMessage('hello'));

        $this->assertCount(2, $history->getMessages());
        $this->assertSame(2, StudioChatMessage::query()->where('thread_id', $threadId)->count());
        $this->assertNull($history->compactionMeta());
    }

    private function pad(string $label, int $chars): string
    {
        return $label.' '.str_repeat('x', max(0, $chars - strlen($label) - 1));
    }
}
