<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Memory;

use NeuronAI\Chat\History\InMemoryChatHistory;
use NeuronAI\Chat\Messages\Message;

/**
 * In-memory history with the same trim metadata as StudioEloquentChatHistory.
 */
class StudioInMemoryChatHistory extends InMemoryChatHistory
{
    use NonDestructiveHistoryTrim;
    use ToolResultBudgeting;

    public function __construct(
        int $contextWindow = 50000,
        protected bool $summarization = false,
        ?int $toolResultBudget = null,
    ) {
        parent::__construct($contextWindow);
        $this->toolResultBudget = $toolResultBudget;
    }

    public function addMessage(Message $message): \NeuronAI\Chat\History\ChatHistoryInterface
    {
        $message = $this->maybeTruncateToolResult($message);

        return parent::addMessage($message);
    }

    protected function summarizationEnabled(): bool
    {
        return $this->summarization;
    }
}
