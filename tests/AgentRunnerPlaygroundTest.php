<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioTraceSpan;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Usage;
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

    public function test_stream_records_llm_span_with_tokens_and_cost(): void
    {
        config([
            'neuronai-studio.usage.pricing.openai.gpt-4o-mini' => [
                'prompt_per_1k' => 0.00015,
                'completion_per_1k' => 0.0006,
            ],
        ]);

        $agent = AgentDefinition::create([
            'name' => 'Metered Stream Agent',
            'slug' => 'metered-stream-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        $response = (new AssistantMessage('streamed'))->setUsage(new Usage(1000, 500));
        $provider = new FakeAIProvider($response);

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($provider);

        $toolResolver = $this->createMock(ToolResolver::class);
        $toolResolver->method('resolveMany')->willReturn([]);

        $runner = new AgentRunner(
            $registry,
            $toolResolver,
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );

        foreach ($runner->stream($agent, ['message' => 'hi']) as $chunk) {
        }

        $run = StudioRun::query()->latest('started_at')->first();
        $this->assertNotNull($run);
        $this->assertSame('completed', $run->status);

        $span = StudioTraceSpan::query()
            ->whereHas('trace', fn ($q) => $q->where('run_id', $run->id))
            ->where('type', 'llm')
            ->first();

        $this->assertNotNull($span);
        $this->assertSame('openai', $span->provider);
        $this->assertSame('gpt-4o-mini', $span->model);
        $this->assertSame(1000, $span->prompt_tokens);
        $this->assertSame(500, $span->completion_tokens);
        $this->assertSame('0.000450', $span->estimated_cost);
        $this->assertSame('0.000450', $run->fresh()->estimated_cost);
    }

    public function test_stream_handler_records_llm_span_when_events_consumed(): void
    {
        config([
            'neuronai-studio.usage.pricing.openai.gpt-4o-mini' => [
                'prompt_per_1k' => 0.00015,
                'completion_per_1k' => 0.0006,
            ],
        ]);

        $agent = AgentDefinition::create([
            'name' => 'Metered Handler Agent',
            'slug' => 'metered-handler-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        $response = (new AssistantMessage('handler'))->setUsage(new Usage(2000, 0));
        $provider = new FakeAIProvider($response);

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($provider);

        $toolResolver = $this->createMock(ToolResolver::class);
        $toolResolver->method('resolveMany')->willReturn([]);

        $runner = new AgentRunner(
            $registry,
            $toolResolver,
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );

        $handler = $runner->streamHandler($agent, ['message' => 'hi']);
        foreach ($handler->events() as $event) {
        }

        $run = StudioRun::query()->latest('started_at')->first();
        $this->assertNotNull($run);

        $span = StudioTraceSpan::query()
            ->whereHas('trace', fn ($q) => $q->where('run_id', $run->id))
            ->where('type', 'llm')
            ->first();

        $this->assertNotNull($span);
        $this->assertSame(2000, $span->prompt_tokens);
        $this->assertSame('0.000300', $span->estimated_cost);
    }
}
