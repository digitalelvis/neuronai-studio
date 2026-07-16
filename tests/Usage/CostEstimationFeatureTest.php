<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Usage;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioTraceSpan;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\AgentNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\LlmNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\NodeExecutorRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\StructuredOutput\StructuredOutputResolver;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Testing\FakeAIProvider;

class CostEstimationFeatureTest extends TestCase
{
    public function test_custom_pricing_override_is_reflected_in_estimated_cost(): void
    {
        config([
            'neuronai-studio.usage.pricing.openai.gpt-4o-mini' => [
                'prompt_per_1k' => 1.0,
                'completion_per_1k' => 2.0,
            ],
        ]);

        $runner = $this->makeAgentRunner(
            (new AssistantMessage('priced'))->setUsage(new Usage(1000, 500)),
        );

        $result = $runner->runInline([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ], 'Hi');

        $run = StudioRun::query()->findOrFail($result->runId);
        // (1000/1000)*1 + (500/1000)*2 = 2.0
        $this->assertSame('2.000000', $run->estimated_cost);

        $span = StudioTraceSpan::query()
            ->whereHas('trace', fn ($q) => $q->where('run_id', $run->id))
            ->where('type', 'llm')
            ->first();

        $this->assertNotNull($span);
        $this->assertSame('openai', $span->provider);
        $this->assertSame('gpt-4o-mini', $span->model);
        $this->assertSame('2.000000', $span->estimated_cost);
    }

    public function test_unpriced_model_yields_zero_estimated_cost(): void
    {
        $runner = $this->makeAgentRunner(
            (new AssistantMessage('free'))->setUsage(new Usage(1000, 500)),
        );

        $result = $runner->runInline([
            'provider' => 'openai',
            'model' => 'custom-unpriced-model',
            'instructions' => 'You are helpful.',
        ], 'Hi');

        $run = StudioRun::query()->findOrFail($result->runId);
        $this->assertSame(1500, $run->total_tokens);
        $this->assertSame('0.000000', $run->estimated_cost);
    }

    public function test_catalog_default_pricing_produces_nonzero_cost(): void
    {
        $rates = config('neuronai-studio.usage.pricing.openai.gpt-4o-mini');
        $this->assertIsArray($rates);
        $this->assertArrayHasKey('prompt_per_1k', $rates);

        $runner = $this->makeAgentRunner(
            (new AssistantMessage('catalog'))->setUsage(new Usage(1000, 0)),
        );

        $result = $runner->runInline([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ], 'Hi');

        $run = StudioRun::query()->findOrFail($result->runId);
        $expected = number_format((float) $rates['prompt_per_1k'], 6, '.', '');
        $this->assertSame($expected, $run->estimated_cost);
        $this->assertNotSame('0.000000', $run->estimated_cost);
    }

    public function test_nested_agent_under_workflow_rolls_up_parent_totals(): void
    {
        config([
            'neuronai-studio.usage.pricing.openai.gpt-4o-mini' => [
                'prompt_per_1k' => 0.00015,
                'completion_per_1k' => 0.0006,
            ],
        ]);

        $agent = AgentDefinition::create([
            'name' => 'Cost Nested Agent',
            'slug' => 'cost-nested-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        $workflow = WorkflowDefinition::create([
            'name' => 'Cost Nested Flow',
            'slug' => 'cost-nested-flow',
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    ['id' => 'agent_1', 'type' => 'agent', 'position' => ['x' => 200, 'y' => 0], 'data' => [
                        'agent_id' => $agent->id,
                        'output_key' => 'agent_response',
                    ]],
                    ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 400, 'y' => 0], 'data' => []],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start_1', 'target' => 'agent_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e2', 'source' => 'agent_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ],
            ],
        ]);

        $this->bindWorkflowAgent(
            (new AssistantMessage('nested'))->setUsage(new Usage(1000, 500)),
        );

        $parent = app(WorkflowRunner::class)->run($workflow, ['message' => 'Hi']);

        $this->assertSame('completed', $parent->status);

        $child = StudioRun::query()->where('parent_run_id', $parent->id)->first();
        $this->assertNotNull($child);
        $this->assertSame(1500, $child->total_tokens);
        $this->assertSame('0.000450', $child->estimated_cost);

        $parent = $parent->fresh();
        $this->assertSame(1500, $parent->total_tokens);
        $this->assertSame('0.000450', $parent->estimated_cost);
    }

    public function test_workflow_llm_node_meters_onto_parent_run(): void
    {
        config([
            'neuronai-studio.usage.pricing.openai.gpt-4o-mini' => [
                'prompt_per_1k' => 0.00015,
                'completion_per_1k' => 0.0006,
            ],
        ]);

        $workflow = WorkflowDefinition::create([
            'name' => 'Cost LLM Flow',
            'slug' => 'cost-llm-flow',
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    ['id' => 'llm_1', 'type' => 'llm', 'position' => ['x' => 200, 'y' => 0], 'data' => [
                        'prompt' => '$input',
                        'provider' => 'openai',
                        'model' => 'gpt-4o-mini',
                        'output_key' => 'llm_response',
                    ]],
                    ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 400, 'y' => 0], 'data' => []],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start_1', 'target' => 'llm_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e2', 'source' => 'llm_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ],
            ],
        ]);

        $this->bindWorkflowLlm(
            (new AssistantMessage('direct'))->setUsage(new Usage(1000, 0)),
        );

        $run = app(WorkflowRunner::class)->run($workflow, ['message' => 'Hi']);

        $this->assertSame('completed', $run->status);
        $this->assertSame(1000, $run->fresh()->total_tokens);
        $this->assertSame('0.000150', $run->fresh()->estimated_cost);
        $this->assertSame(0, StudioRun::query()->where('parent_run_id', $run->id)->count());
    }

    protected function makeAgentRunner(AssistantMessage ...$responses): AgentRunner
    {
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn(new FakeAIProvider(...$responses));

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

    protected function bindWorkflowAgent(AssistantMessage ...$responses): void
    {
        $runner = $this->makeAgentRunner(...$responses);

        $this->app->instance(AgentRunner::class, $runner);
        $this->app->make(NodeExecutorRegistry::class)->register(
            'agent',
            new AgentNodeExecutor($runner, new MessageFactory, app(StructuredOutputResolver::class)),
        );
    }

    protected function bindWorkflowLlm(AssistantMessage ...$responses): void
    {
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn(new FakeAIProvider(...$responses));

        $toolResolver = $this->createMock(ToolResolver::class);
        $toolResolver->method('resolveMany')->willReturn([]);

        $runner = new AgentRunner(
            $registry,
            $toolResolver,
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );

        $this->app->instance(ProviderRegistry::class, $registry);
        $this->app->instance(AgentRunner::class, $runner);
        $this->app->make(NodeExecutorRegistry::class)->register(
            'llm',
            new LlmNodeExecutor(
                $registry,
                $runner,
                new MessageFactory,
                app(StructuredOutputResolver::class),
            ),
        );
    }
}
