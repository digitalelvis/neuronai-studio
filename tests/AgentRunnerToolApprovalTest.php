<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\ToolApprovalRequiredException;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Tools\Tool;

class AgentRunnerToolApprovalTest extends TestCase
{
    protected function makeRunner(FakeAIProvider $provider): AgentRunner
    {
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($provider);

        $toolResolver = $this->createMock(ToolResolver::class);
        $toolResolver->method('resolveMany')->willReturn([]);

        return new AgentRunner(
            $registry,
            $toolResolver,
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );
    }

    protected function toolCall(): ToolCallMessage
    {
        $tool = Tool::make('delete_file', 'Deletes a file')
            ->setInputs(['path' => '/tmp/report.txt'])
            ->setCallId('call_1');

        return new ToolCallMessage(null, [$tool]);
    }

    public function test_run_inline_pauses_for_tool_approval_when_enabled(): void
    {
        $runner = $this->makeRunner(new FakeAIProvider($this->toolCall()));

        try {
            $runner->runInline([
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'instructions' => 'You are helpful.',
                'tools' => [],
                'require_tool_approval' => true,
            ], 'Delete the report');

            $this->fail('Expected ToolApprovalRequiredException was not thrown.');
        } catch (ToolApprovalRequiredException $exception) {
            $this->assertSame('', $exception->nodeId);
            $this->assertCount(1, $exception->pendingTools);
            $this->assertSame('delete_file', $exception->pendingTools[0]['name']);
            $this->assertSame(['path' => '/tmp/report.txt'], $exception->pendingTools[0]['arguments']);
            $this->assertSame('call_1', $exception->pendingTools[0]['call_id']);
        }
    }

    public function test_run_inline_does_not_pause_when_approval_disabled(): void
    {
        $runner = $this->makeRunner(new FakeAIProvider(new AssistantMessage('Just an answer')));

        $result = $runner->runInline([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
            'tools' => [],
            'require_tool_approval' => false,
        ], 'Say hi');

        $this->assertSame('Just an answer', $result->content);
    }
}
