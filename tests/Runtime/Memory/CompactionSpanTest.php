<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime\Memory;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioChatMessage;
use DigitalElvis\NeuronAIStudio\Models\StudioTraceSpan;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Support\ChatThreadKey;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;

class CompactionSpanTest extends TestCase
{
    public function test_records_compaction_span_when_native_tracing_enabled(): void
    {
        config([
            'neuronai-studio.observability.native_tracing' => true,
            'neuronai-studio.memory.summarizer.provider' => null,
            'neuronai-studio.memory.summarizer.model' => null,
        ]);

        $agent = AgentDefinition::create([
            'name' => 'Span Agent',
            'slug' => 'span-agent-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
            'tools' => [],
            'memory_config' => [
                'context_window' => 120,
                'summarization_enabled' => false,
            ],
        ]);

        $threadId = (string) \Illuminate\Support\Str::uuid();
        $scoped = ChatThreadKey::forAgent($agent->id, $threadId);
        $pad = fn (string $l, int $n) => $l.' '.str_repeat('x', max(0, $n - strlen($l) - 1));

        StudioChatMessage::create([
            'thread_id' => $scoped,
            'role' => 'user',
            'content' => [['type' => 'text', 'content' => $pad('u1', 80)]],
        ]);
        StudioChatMessage::create([
            'thread_id' => $scoped,
            'role' => 'assistant',
            'content' => [['type' => 'text', 'content' => $pad('a1', 80)]],
        ]);
        StudioChatMessage::create([
            'thread_id' => $scoped,
            'role' => 'user',
            'content' => [['type' => 'text', 'content' => $pad('u2', 80)]],
        ]);
        StudioChatMessage::create([
            'thread_id' => $scoped,
            'role' => 'assistant',
            'content' => [['type' => 'text', 'content' => $pad('a2', 80)]],
        ]);

        $runner = $this->runnerWithFake(new FakeAIProvider(new AssistantMessage('ok')));
        $runner->runInline(
            [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'instructions' => 'Test',
                'tools' => [],
                'context_window' => 120,
            ],
            'next turn',
            $agent,
            $scoped,
        );

        $span = StudioTraceSpan::query()->where('type', 'memory')->where('name', 'history_compaction')->first();
        $this->assertNotNull($span);
        $this->assertSame('non_destructive', $span->output['mode'] ?? null);
        $this->assertArrayHasKey('trimmed_count', $span->output ?? []);
    }

    public function test_skips_span_when_native_tracing_disabled(): void
    {
        config([
            'neuronai-studio.observability.native_tracing' => false,
        ]);

        $agent = AgentDefinition::create([
            'name' => 'No Span Agent',
            'slug' => 'no-span-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
            'tools' => [],
            'memory_config' => ['context_window' => 120],
        ]);

        $threadId = (string) \Illuminate\Support\Str::uuid();
        $scoped = ChatThreadKey::forAgent($agent->id, $threadId);
        $pad = fn (string $l, int $n) => $l.' '.str_repeat('x', max(0, $n - strlen($l) - 1));

        foreach ([['user', 'u1'], ['assistant', 'a1'], ['user', 'u2'], ['assistant', 'a2']] as [$role, $label]) {
            StudioChatMessage::create([
                'thread_id' => $scoped,
                'role' => $role,
                'content' => [['type' => 'text', 'content' => $pad($label, 80)]],
            ]);
        }

        $runner = $this->runnerWithFake(new FakeAIProvider(new AssistantMessage('ok')));
        $runner->runInline(
            [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'instructions' => 'Test',
                'tools' => [],
                'context_window' => 120,
            ],
            'next',
            $agent,
            $scoped,
        );

        $this->assertSame(0, StudioTraceSpan::query()->where('type', 'memory')->count());
    }

    private function runnerWithFake(FakeAIProvider $provider): AgentRunner
    {
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($provider);

        $tools = $this->createMock(ToolResolver::class);
        $tools->method('resolveMany')->willReturn([]);

        return new AgentRunner(
            $registry,
            $tools,
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );
    }
}
