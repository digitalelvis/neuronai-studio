<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\StudioTraceSpan;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Classifier\ClassifierInterface;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Testing\FakeClassifier;

class AgentRunnerRoutingTest extends TestCase
{
    /** @return array<string, mixed> */
    protected function routingConfig(): array
    {
        return [
            'enabled' => true,
            'easy_max' => 0.33,
            'medium_max' => 0.70,
            'tiers' => [
                'easy' => ['provider' => 'openai', 'model' => 'gpt-4o-mini', 'api_key' => null],
                'medium' => ['provider' => 'openai', 'model' => 'gpt-4o', 'api_key' => null],
                'hard' => ['provider' => 'openai', 'model' => 'o3', 'api_key' => null],
            ],
        ];
    }

    protected function runner(ProviderRegistry $registry): AgentRunner
    {
        return new AgentRunner(
            $registry,
            $this->createMock(ToolResolver::class),
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );
    }

    public function test_routes_three_turns_to_easy_medium_and_hard(): void
    {
        $this->app->instance(ClassifierInterface::class, new FakeClassifier([
            ['difficulty' => [1.0, 0.0, 0.0]],
            ['difficulty' => [0.0, 1.0, 0.0]],
            ['difficulty' => [0.0, 0.0, 1.0]],
        ]));

        $providers = [
            'gpt-4o-mini' => new FakeAIProvider(new AssistantMessage('from-mini')),
            'gpt-4o' => new FakeAIProvider(new AssistantMessage('from-4o')),
            'o3' => new FakeAIProvider(new AssistantMessage('from-o3')),
        ];

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturnCallback(
            fn (string $provider, ?string $model): FakeAIProvider => $providers[$model] ?? $providers['o3'],
        );

        $runner = $this->runner($registry);
        $config = [
            'provider' => 'openai',
            'model' => 'o3',
            'instructions' => 'You are helpful.',
            'routing_config' => $this->routingConfig(),
        ];

        $easy = $runner->runInline($config, 'easy please');
        $medium = $runner->runInline($config, 'think a bit');
        $hard = $runner->runInline($config, 'hard problem');

        $this->assertSame('from-mini', $easy->content);
        $this->assertSame('from-4o', $medium->content);
        $this->assertSame('from-o3', $hard->content);

        $providers['gpt-4o-mini']->assertMethodCallCount('chat', 1);
        $providers['gpt-4o']->assertMethodCallCount('chat', 1);
        $providers['o3']->assertMethodCallCount('chat', 1);

        $this->assertSame('gpt-4o-mini', $this->spanForRun($easy->runId, 'llm_inference')->model);
        $this->assertSame('gpt-4o', $this->spanForRun($medium->runId, 'llm_inference')->model);
        $this->assertSame('o3', $this->spanForRun($hard->runId, 'llm_inference')->model);

        $routing = $this->spanForRun($easy->runId, 'model_routing');
        $this->assertSame('easy', $routing->output['tier']);
        $this->assertSame('medium', $this->spanForRun($medium->runId, 'model_routing')->output['tier']);
        $this->assertSame('hard', $this->spanForRun($hard->runId, 'model_routing')->output['tier']);
        $this->assertSame('classifier', $routing->type);
        $this->assertSame(0, (int) $routing->estimated_cost);
    }

    protected function spanForRun(?string $runId, string $name): StudioTraceSpan
    {
        $traceIds = \DigitalElvis\NeuronAIStudio\Models\StudioTrace::query()
            ->where('run_id', $runId)
            ->pluck('id');

        $span = StudioTraceSpan::query()
            ->whereIn('trace_id', $traceIds)
            ->where('name', $name)
            ->first();

        $this->assertNotNull($span);

        return $span;
    }

    public function test_routing_fails_closed_without_a_typesafe_key(): void
    {
        config(['neuronai-studio.classifier.key' => null]);

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->expects($this->never())->method('resolve');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TYPESAFE_KEY');

        $this->runner($registry)->runInline([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
            'routing_config' => $this->routingConfig(),
        ], 'Hi');
    }

    public function test_routing_off_uses_the_single_configured_model(): void
    {
        $provider = new FakeAIProvider(new AssistantMessage('Hello back'));
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->expects($this->once())->method('resolve')->with('openai', 'gpt-4o-mini')->willReturn($provider);

        $result = $this->runner($registry)->runInline([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ], 'Hi');

        $this->assertSame('Hello back', $result->content);
        $this->assertSame(0, StudioTraceSpan::query()->where('name', 'model_routing')->count());
    }
}
