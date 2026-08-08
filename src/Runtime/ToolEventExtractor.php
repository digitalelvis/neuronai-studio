<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use NeuronAI\Chat\History\ChatHistoryInterface;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Chat\Messages\ToolResultMessage;
use NeuronAI\Chat\Messages\UserMessage;

class ToolEventExtractor
{
    /**
     * Extract tool call/result events from the current turn only (messages after
     * the latest user message). Full-thread extraction would re-emit prior-turn
     * tools onto the latest playground/assistant bubble.
     *
     * @return array<int, array{name: string, inputs: array<string, mixed>, result: string|null, type: string}>
     */
    public function fromChatHistory(ChatHistoryInterface $history): array
    {
        $messages = array_values($history->getMessages());
        $start = 0;

        foreach ($messages as $index => $message) {
            // ToolResultMessage extends UserMessage — do not treat results as turn boundaries.
            if ($message instanceof UserMessage && ! $message instanceof ToolResultMessage) {
                $start = $index + 1;
            }
        }

        $events = [];

        for ($index = $start, $count = count($messages); $index < $count; $index++) {
            $message = $messages[$index];

            if ($message instanceof ToolCallMessage) {
                foreach ($message->getTools() as $tool) {
                    $events[] = [
                        'name' => $tool->getName(),
                        'inputs' => $tool->getInputs(),
                        'result' => null,
                        'type' => 'call',
                    ];
                }
            }

            if ($message instanceof ToolResultMessage) {
                foreach ($message->getTools() as $tool) {
                    $events[] = [
                        'name' => $tool->getName(),
                        'inputs' => $tool->getInputs(),
                        'result' => $tool->getResult(),
                        'type' => 'result',
                    ];
                }
            }
        }

        return $events;
    }
}
