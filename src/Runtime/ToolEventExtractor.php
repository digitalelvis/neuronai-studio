<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime;

use NeuronAI\Chat\History\ChatHistoryInterface;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Chat\Messages\ToolResultMessage;

class ToolEventExtractor
{
    /**
     * @return array<int, array{name: string, inputs: array<string, mixed>, result: string|null, type: string}>
     */
    public function fromChatHistory(ChatHistoryInterface $history): array
    {
        $events = [];

        foreach ($history->getMessages() as $message) {
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
