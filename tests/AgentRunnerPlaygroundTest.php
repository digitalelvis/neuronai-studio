<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;

class AgentRunnerPlaygroundTest extends TestCase
{
    public function test_stream_applies_context_and_parameters(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Context Agent',
            'slug' => 'context-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        $provider = new FakeAIProvider(new AssistantMessage('Your plan is gold.'));

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->expects($this->once())
            ->method('resolve')
            ->with(
                'openai',
                'gpt-4o-mini',
                $this->callback(static fn (array $parameters) => ($parameters['temperature'] ?? null) === 0.2),
            )
            ->willReturn($provider);

        $toolResolver = $this->createMock(ToolResolver::class);
        $toolResolver->method('resolveMany')->willReturn([]);

        $runner = new AgentRunner(
            $registry,
            $toolResolver,
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );

        foreach ($runner->stream($agent, [
            'message' => 'qual meu plano?',
            'context' => ['plan' => 'gold'],
            'parameters' => ['temperature' => 0.2],
        ]) as $chunk) {
        }

        $recorded = $provider->getRecorded();
        $this->assertNotEmpty($recorded);
        $this->assertStringContainsString('gold', (string) $recorded[0]->systemPrompt);
    }
}
