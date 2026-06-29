<?php

namespace ElvisLopesDigital\NeuronAIStudio\Tests;

use ElvisLopesDigital\NeuronAIStudio\Registry\ProviderRegistry;
use ElvisLopesDigital\NeuronAIStudio\Runtime\AgentRunner;
use ElvisLopesDigital\NeuronAIStudio\Runtime\McpToolResolver;
use ElvisLopesDigital\NeuronAIStudio\Runtime\MessageFactory;
use ElvisLopesDigital\NeuronAIStudio\Runtime\ToolEventExtractor;
use ElvisLopesDigital\NeuronAIStudio\Runtime\ToolResolver;
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
