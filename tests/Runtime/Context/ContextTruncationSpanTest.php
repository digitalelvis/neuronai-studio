<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime\Context;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioTraceSpan;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\AgentNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\StructuredOutput\StructuredOutputResolver;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Tools\Tool;

class ContextTruncationSpanTest extends TestCase
{
    public function test_records_tool_truncation_span_when_native_tracing_on(): void
    {
        config(['neuronai-studio.observability.native_tracing' => true]);

        $callable = fn () => str_repeat('B', 50000);
        $tool = Tool::make('verbose_dump', 'dump')->setCallable($callable);
        $call = Tool::make('verbose_dump', 'dump')
            ->setCallable($callable)
            ->setInputs([])
            ->setCallId('c1');

        $provider = new FakeAIProvider(
            new ToolCallMessage(null, [$call]),
            new AssistantMessage('ok'),
        );

        $runner = $this->runnerWith($provider, [$tool]);
        $definition = AgentDefinition::create([
            'name' => 'Ctx Span Agent',
            'slug' => 'ctx-span-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
            'tools' => [],
            'memory_config' => ['budget_tool_results' => 800],
        ]);

        $runner->runInline([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
            'tools' => [['ref' => 'x']],
            'budget_tool_results' => 800,
        ], 'go', $definition);

        $span = StudioTraceSpan::query()
            ->where('type', 'context')
            ->where('name', 'context_truncation')
            ->first();

        $this->assertNotNull($span);
        $this->assertSame('tool_result', $span->output['kind'] ?? null);
        $this->assertSame('verbose_dump', $span->output['tool'] ?? null);
        $this->assertArrayHasKey('tokens_before', $span->output ?? []);
        $this->assertArrayHasKey('tokens_after', $span->output ?? []);
    }

    public function test_truncation_still_applies_when_native_tracing_off(): void
    {
        config(['neuronai-studio.observability.native_tracing' => false]);

        $callable = fn () => str_repeat('C', 50000);
        $tool = Tool::make('verbose_dump', 'dump')->setCallable($callable);
        $call = Tool::make('verbose_dump', 'dump')
            ->setCallable($callable)
            ->setInputs([])
            ->setCallId('c2');

        $provider = new FakeAIProvider(
            new ToolCallMessage(null, [$call]),
            new AssistantMessage('ok'),
        );

        $runner = $this->runnerWith($provider, [$tool]);
        $definition = AgentDefinition::create([
            'name' => 'No Trace Agent',
            'slug' => 'no-trace-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
            'tools' => [],
            'memory_config' => ['budget_tool_results' => 800],
        ]);

        $result = $runner->runInline([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
            'tools' => [['ref' => 'x']],
            'budget_tool_results' => 800,
        ], 'go', $definition);

        $this->assertSame('ok', $result->content);
        $this->assertSame(0, StudioTraceSpan::query()->where('type', 'context')->count());
        $toolResults = array_values(array_filter(
            $result->toolEvents,
            fn (array $e) => ($e['type'] ?? '') === 'result',
        ));
        $this->assertStringContainsString('[truncated]', (string) ($toolResults[0]['result'] ?? ''));
    }

    public function test_agent_node_records_rag_truncation_span(): void
    {
        config(['neuronai-studio.observability.native_tracing' => true]);

        $provider = new FakeAIProvider(new AssistantMessage('answered'));
        $runner = $this->runnerWith($provider, []);

        $definition = AgentDefinition::create([
            'name' => 'RAG Span Agent',
            'slug' => 'rag-span-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
            'tools' => [],
            'memory_config' => ['budget_rag' => 20],
        ]);

        $executor = new AgentNodeExecutor(
            $runner,
            new MessageFactory,
            app(StructuredOutputResolver::class),
        );

        $contextText = str_repeat('Retrieval sentence one. ', 40)
            ."\n\n---\n\n"
            .str_repeat('Retrieval sentence two. ', 40);

        $state = new BuilderWorkflowState(new GraphContext([
            ['id' => 'a1', 'type' => 'agent', 'data' => [
                'agent_id' => $definition->id,
                'message' => 'Use {{rag_context.context}}',
            ]],
        ], []), null, [
            'rag_context' => ['context' => $contextText],
            'input' => 'hi',
        ]);

        // Minimal graph: single agent node — GraphContext needs edges for runner,
        // but AgentNodeExecutor::execute does not need a full workflow.
        $executor->execute([
            'id' => 'a1',
            'type' => 'agent',
            'data' => [
                'agent_id' => $definition->id,
                'message' => 'Use {{rag_context.context}}',
            ],
        ], $state, new GraphContext([], []));

        $span = StudioTraceSpan::query()
            ->where('type', 'context')
            ->where('name', 'context_truncation')
            ->first();

        $this->assertNotNull($span);
        $this->assertSame('rag_context', $span->output['kind'] ?? null);
    }

    /**
     * @param  list<\NeuronAI\Tools\ToolInterface>  $tools
     */
    private function runnerWith(FakeAIProvider $provider, array $tools): AgentRunner
    {
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($provider);

        $toolResolver = $this->createMock(ToolResolver::class);
        $toolResolver->method('resolveMany')->willReturn($tools);

        return new AgentRunner(
            $registry,
            $toolResolver,
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );
    }
}
