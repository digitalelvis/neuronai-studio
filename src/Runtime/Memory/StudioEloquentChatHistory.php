<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Memory;

use NeuronAI\Chat\History\EloquentChatHistory;
use NeuronAI\Chat\Messages\Message;

/**
 * Eloquent history that never silently deletes on trim; with summarization
 * enabled, compaction rewrites the thread to summary + retained suffix.
 */
class StudioEloquentChatHistory extends EloquentChatHistory
{
    use NonDestructiveHistoryTrim;
    use ToolResultBudgeting;

    public function __construct(
        string $threadId,
        string $modelClass,
        int $contextWindow = 50000,
        protected bool $summarization = false,
        ?int $toolResultBudget = null,
    ) {
        parent::__construct($threadId, $modelClass, $contextWindow);
        $this->toolResultBudget = $toolResultBudget;
    }

    public function addMessage(Message $message): \NeuronAI\Chat\History\ChatHistoryInterface
    {
        $message = $this->maybeTruncateToolResult($message);
        $this->history[] = $message;

        $this->trimHistory();

        if (! $this->tookStorageRewrite()) {
            $this->onNewMessage($message);
        }

        $this->setMessages($this->history);

        return $this;
    }

    protected function summarizationEnabled(): bool
    {
        return $this->summarization;
    }

    /**
     * @param  Message[]  $messages
     */
    protected function persistCompactedHistory(array $messages): void
    {
        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $this->modelClass;

        $model->newQuery()->where('thread_id', $this->threadId)->delete();

        foreach ($messages as $message) {
            $model->newQuery()->create([
                'thread_id' => $this->threadId,
                'role' => $message->getRole(),
                'content' => $message->getContentBlocks(),
                'meta' => $this->serializeMessageMeta($message),
            ]);
        }

        $this->storageRewritten = true;
    }
}
