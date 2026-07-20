<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Memory;

use NeuronAI\Chat\History\EloquentChatHistory;

/**
 * Eloquent history that never physically deletes trimmed messages.
 * Prompt path uses the trimmed in-memory history only.
 */
class StudioEloquentChatHistory extends EloquentChatHistory
{
    use NonDestructiveHistoryTrim;

    public function __construct(
        string $threadId,
        string $modelClass,
        int $contextWindow = 50000,
        protected bool $summarization = false,
    ) {
        parent::__construct($threadId, $modelClass, $contextWindow);
    }

    protected function summarizationEnabled(): bool
    {
        return $this->summarization;
    }
}
