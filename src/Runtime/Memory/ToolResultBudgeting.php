<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Memory;

use DigitalElvis\NeuronAIStudio\Runtime\Context\ToolResultBudgeter;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\ToolResultMessage;

/**
 * Optional tool-result token budget applied when messages enter history.
 */
trait ToolResultBudgeting
{
    protected ?int $toolResultBudget = null;

    /** @var list<array<string, mixed>> */
    protected array $toolTruncationEvents = [];

    public function withToolResultBudget(?int $budget): static
    {
        $this->toolResultBudget = $budget;

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pullToolTruncationEvents(): array
    {
        $events = $this->toolTruncationEvents;
        $this->toolTruncationEvents = [];

        return $events;
    }

    protected function maybeTruncateToolResult(Message $message): Message
    {
        if (! $message instanceof ToolResultMessage || $this->toolResultBudget === null) {
            return $message;
        }

        [$truncated, $events] = (new ToolResultBudgeter)->apply($message, $this->toolResultBudget);
        foreach ($events as $event) {
            $this->toolTruncationEvents[] = $event;
        }

        return $truncated;
    }
}
