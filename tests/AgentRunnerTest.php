<?php

namespace ElvisLopesDigital\NeuronAIStudio\Tests;

use ElvisLopesDigital\NeuronAIStudio\Registry\ProviderRegistry;
use ElvisLopesDigital\NeuronAIStudio\Runtime\AgentRunner;
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
            ->with('openai', 'gpt-4o-mini')
            ->willReturn($provider);

        $runner = new AgentRunner($registry);

        $response = $runner->runInline([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ], 'Hi');

        $this->assertSame('Hello back', $response);
    }
}
