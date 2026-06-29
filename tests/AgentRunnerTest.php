<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;

class AgentRunnerTest extends TestCase
{
    public function test_run_inline_returns_provider_response(): void
    {
        $provider = new FakeAIProvider(new AssistantMessage('Hello back'));

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->expects($this->once())
            ->method('resolve')
            ->with('openai', 'gpt-4o-mini', [])
            ->willReturn($provider);

        $toolResolver = $this->createMock(ToolResolver::class);
        $toolResolver->method('resolveMany')->willReturn([]);

        $mcpToolResolver = $this->createMock(McpToolResolver::class);

        $runner = new AgentRunner($registry, $toolResolver, $mcpToolResolver, new ToolEventExtractor, new MessageFactory);

        $result = $runner->runInline([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ], 'Hi');

        $this->assertSame('Hello back', $result->content);
    }
}
