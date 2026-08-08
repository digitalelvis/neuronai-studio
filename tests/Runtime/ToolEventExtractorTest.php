<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime;

use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use NeuronAI\Chat\History\InMemoryChatHistory;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Chat\Messages\ToolResultMessage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Tools\Tool;

class ToolEventExtractorTest extends TestCase
{
    public function test_from_chat_history_scopes_events_to_current_turn(): void
    {
        $history = new InMemoryChatHistory;
        $history->addMessage(new UserMessage('first turn'));
        $history->addMessage($this->toolCall('get_user_profile', ['id' => 1]));
        $history->addMessage($this->toolResult('get_user_profile', ['id' => 1], '{"ok":true}'));
        $history->addMessage(new AssistantMessage('done first'));

        $history->addMessage(new UserMessage('second turn'));
        $history->addMessage($this->toolCall('handoff_to_human', ['reason' => 'escalate']));
        $history->addMessage($this->toolResult('handoff_to_human', ['reason' => 'escalate'], 'queued'));
        $history->addMessage(new AssistantMessage('done second'));

        $events = (new ToolEventExtractor)->fromChatHistory($history);

        $this->assertCount(2, $events);
        $this->assertSame('handoff_to_human', $events[0]['name']);
        $this->assertSame('call', $events[0]['type']);
        $this->assertSame('handoff_to_human', $events[1]['name']);
        $this->assertSame('result', $events[1]['type']);
        $this->assertSame('queued', $events[1]['result']);
    }

    public function test_from_chat_history_includes_tools_when_no_prior_user_message(): void
    {
        $history = new InMemoryChatHistory;
        $history->addMessage($this->toolCall('mbft_tool', []));
        $history->addMessage($this->toolResult('mbft_tool', [], 'ok'));

        $events = (new ToolEventExtractor)->fromChatHistory($history);

        $this->assertCount(2, $events);
        $this->assertSame('mbft_tool', $events[0]['name']);
        $this->assertSame('mbft_tool', $events[1]['name']);
    }

    protected function toolCall(string $name, array $inputs): ToolCallMessage
    {
        $tool = Tool::make($name, $name)
            ->setInputs($inputs)
            ->setCallId('call_'.$name);

        return new ToolCallMessage(null, [$tool]);
    }

    protected function toolResult(string $name, array $inputs, string $result): ToolResultMessage
    {
        $tool = Tool::make($name, $name)
            ->setInputs($inputs)
            ->setCallId('call_'.$name)
            ->setResult($result);

        return new ToolResultMessage([$tool]);
    }
}
