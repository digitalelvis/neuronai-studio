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

class AgentRunnerToolsTest extends TestCase
{
    public function test_run_inline_returns_agent_run_result(): void
    {
        $provider = new FakeAIProvider(new AssistantMessage('Hello back'));

        $providerRegistry = $this->createMock(ProviderRegistry::class);
        $providerRegistry->expects($this->once())
            ->method('resolve')
            ->with('openai', 'gpt-4o-mini')
            ->willReturn($provider);

        $toolResolver = $this->createMock(ToolResolver::class);
        $toolResolver->expects($this->once())
            ->method('resolveMany')
            ->with([])
            ->willReturn([]);

        $mcpToolResolver = $this->createMock(McpToolResolver::class);

        $runner = new AgentRunner(
            $providerRegistry,
            $toolResolver,
            $mcpToolResolver,
            new ToolEventExtractor,
            new MessageFactory,
        );

        $result = $runner->runInline([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
            'tools' => [],
        ], 'Hi');

        $this->assertSame('Hello back', $result->content);
        $this->assertSame([], $result->toolEvents);
    }

    public function test_run_inline_passes_tool_bindings_to_resolver(): void
    {
        $provider = new FakeAIProvider(new AssistantMessage('Done'));

        $providerRegistry = $this->createMock(ProviderRegistry::class);
        $providerRegistry->method('resolve')->willReturn($provider);

        $bindings = [
            ['ref' => 'toolkit:calculator', 'config' => []],
        ];

        $toolResolver = $this->createMock(ToolResolver::class);
        $toolResolver->expects($this->once())
            ->method('resolveMany')
            ->with($bindings)
            ->willReturn([\NeuronAI\Tools\Toolkits\Calculator\CalculatorToolkit::make()]);

        $mcpToolResolver = $this->createMock(McpToolResolver::class);

        $runner = new AgentRunner(
            $providerRegistry,
            $toolResolver,
            $mcpToolResolver,
            new ToolEventExtractor,
            new MessageFactory,
        );

        $result = $runner->runInline([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Calculate.',
            'tools' => $bindings,
        ], 'What is 2+2?');

        $this->assertSame('Done', $result->content);
    }
}
