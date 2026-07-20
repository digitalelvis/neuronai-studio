<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Memory;

use NeuronAI\Chat\History\InMemoryChatHistory;

/**
 * In-memory history with the same trim metadata as StudioEloquentChatHistory.
 */
class StudioInMemoryChatHistory extends InMemoryChatHistory
{
    use NonDestructiveHistoryTrim;

    public function __construct(
        int $contextWindow = 50000,
        protected bool $summarization = false,
    ) {
        parent::__construct($contextWindow);
    }

    protected function summarizationEnabled(): bool
    {
        return $this->summarization;
    }
}
