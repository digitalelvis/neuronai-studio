<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\StudioTraceSpan;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use Illuminate\Support\Str;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Usage;
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

    public function test_run_inline_records_provider_model_and_cost_on_llm_span(): void
    {
        config([
            'neuronai-studio.usage.pricing.openai.gpt-4o-mini' => [
                'prompt_per_1k' => 0.00015,
                'completion_per_1k' => 0.0006,
            ],
        ]);

        $response = (new AssistantMessage('Hello back'))->setUsage(new Usage(1000, 500));
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

        $result = $runner->runInline([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ], 'Hi');

        $run = StudioRun::query()->findOrFail($result->runId);
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
        $this->assertSame(1500, $run->fresh()->total_tokens);
        $this->assertSame('0.000450', $run->fresh()->estimated_cost);
    }

    public function test_run_inline_persists_parent_run_id_and_rolls_up_tokens(): void
    {
        config([
            'neuronai-studio.usage.pricing.openai.gpt-4o-mini' => [
                'prompt_per_1k' => 0.00015,
                'completion_per_1k' => 0.0006,
            ],
        ]);

        $parent = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => StudioThread::create(['id' => (string) Str::uuid()])->id,
            'status' => 'running',
        ]);

        $response = (new AssistantMessage('nested'))->setUsage(new Usage(1000, 0));
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

        $result = $runner->runInline(
            [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'instructions' => 'You are helpful.',
            ],
            'Hi',
            parentRun: $parent,
        );

        $child = StudioRun::query()->findOrFail($result->runId);

        $this->assertSame($parent->id, $child->parent_run_id);
        $this->assertSame(1000, $child->prompt_tokens);
        $this->assertSame('0.000150', $child->estimated_cost);
        $this->assertSame(1000, $parent->fresh()->prompt_tokens);
        $this->assertSame('0.000150', $parent->fresh()->estimated_cost);
    }
}
