<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime\Memory;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\StudioTrace;
use DigitalElvis\NeuronAIStudio\Models\StudioTraceSpan;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\Memory\HistorySummarizer;
use DigitalElvis\NeuronAIStudio\Runtime\Memory\SummarizeResult;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use Illuminate\Support\Str;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Testing\FakeAIProvider;
use RuntimeException;

class HistorySummarizerTest extends TestCase
{
    public function test_uses_dedicated_model_when_configured(): void
    {
        config([
            'neuronai-studio.memory.summarizer.provider' => 'openai',
            'neuronai-studio.memory.summarizer.model' => 'gpt-4o-mini',
        ]);

        $dedicated = new FakeAIProvider(
            (new AssistantMessage('Dedicated summary'))->setUsage(new Usage(10, 5)),
        );
        $agent = new FakeAIProvider(new AssistantMessage('Agent summary'));

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->expects($this->once())
            ->method('resolve')
            ->with('openai', 'gpt-4o-mini', [])
            ->willReturn($dedicated);

        $result = (new HistorySummarizer($registry))->summarize(
            ['USER: hello', 'ASSISTANT: hi'],
            ['provider' => 'anthropic', 'model' => 'claude-3'],
        );

        $this->assertTrue($result->ok);
        $this->assertSame('Dedicated summary', $result->summary);
        $this->assertSame(SummarizeResult::SOURCE_DEDICATED, $result->source);
        $this->assertSame(['prompt_tokens' => 10, 'completion_tokens' => 5], $result->usage);
    }

    public function test_falls_back_to_agent_model_when_dedicated_unset(): void
    {
        config([
            'neuronai-studio.memory.summarizer.provider' => null,
            'neuronai-studio.memory.summarizer.model' => null,
        ]);

        $agentProvider = new FakeAIProvider(new AssistantMessage('Agent summary'));

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->expects($this->once())
            ->method('resolve')
            ->with('openai', 'gpt-4o', [])
            ->willReturn($agentProvider);

        $result = (new HistorySummarizer($registry))->summarize(
            ['turn one'],
            ['provider' => 'openai', 'model' => 'gpt-4o'],
        );

        $this->assertTrue($result->ok);
        $this->assertSame('Agent summary', $result->summary);
        $this->assertSame(SummarizeResult::SOURCE_AGENT, $result->source);
    }

    public function test_falls_back_to_agent_when_dedicated_fails(): void
    {
        config([
            'neuronai-studio.memory.summarizer.provider' => 'openai',
            'neuronai-studio.memory.summarizer.model' => 'cheap-model',
        ]);

        $failing = $this->createMock(AIProviderInterface::class);
        $failing->method('chat')->willThrowException(new RuntimeException('provider down'));

        $agentProvider = new FakeAIProvider(new AssistantMessage('Recovered summary'));

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->expects($this->exactly(2))
            ->method('resolve')
            ->willReturnCallback(function (string $provider, ?string $model = null) use ($failing, $agentProvider) {
                if ($provider === 'openai' && $model === 'cheap-model') {
                    return $failing;
                }

                return $agentProvider;
            });

        $result = (new HistorySummarizer($registry))->summarize(
            ['turn'],
            ['provider' => 'openai', 'model' => 'gpt-4o'],
        );

        $this->assertTrue($result->ok);
        $this->assertSame('Recovered summary', $result->summary);
        $this->assertSame(SummarizeResult::SOURCE_AGENT, $result->source);
    }

    public function test_failing_providers_return_typed_failure_not_throw(): void
    {
        config([
            'neuronai-studio.memory.summarizer.provider' => null,
            'neuronai-studio.memory.summarizer.model' => null,
        ]);

        $failing = $this->createMock(AIProviderInterface::class);
        $failing->method('chat')->willThrowException(new RuntimeException('boom'));

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($failing);

        $result = (new HistorySummarizer($registry))->summarize(
            ['turn'],
            ['provider' => 'openai', 'model' => 'gpt-4o'],
        );

        $this->assertFalse($result->ok);
        $this->assertSame(SummarizeResult::SOURCE_FAILED, $result->source);
        $this->assertStringContainsString('boom', (string) $result->error);
    }

    public function test_meters_usage_on_run_when_trace_provided(): void
    {
        config([
            'neuronai-studio.memory.summarizer.provider' => null,
            'neuronai-studio.memory.summarizer.model' => null,
            'neuronai-studio.usage.pricing.openai.gpt-4o-mini' => [
                'prompt_per_1k' => 0.00015,
                'completion_per_1k' => 0.0006,
            ],
        ]);

        [$run, $trace] = $this->makeRunAndTrace();

        $provider = new FakeAIProvider(
            (new AssistantMessage('Metered summary'))->setUsage(new Usage(100, 50)),
        );

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($provider);

        $result = (new HistorySummarizer($registry))->summarize(
            ['turn'],
            ['provider' => 'openai', 'model' => 'gpt-4o-mini'],
            $run,
            $trace,
        );

        $this->assertTrue($result->ok);

        $span = StudioTraceSpan::query()->where('trace_id', $trace->id)->first();
        $this->assertNotNull($span);
        $this->assertSame('openai', $span->provider);
        $this->assertSame('gpt-4o-mini', $span->model);
        $this->assertSame(100, $span->prompt_tokens);
        $this->assertSame(50, $span->completion_tokens);

        $run->refresh();
        $this->assertSame(100, $run->prompt_tokens);
        $this->assertSame(50, $run->completion_tokens);
    }

    /**
     * @return array{0: StudioRun, 1: StudioTrace}
     */
    private function makeRunAndTrace(): array
    {
        $thread = StudioThread::create([
            'id' => (string) Str::uuid(),
            'subject_type' => 'agent',
            'subject_id' => (string) Str::uuid(),
            'title' => 'Summarizer test',
        ]);

        $run = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'status' => 'running',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'estimated_cost' => '0.000000',
        ]);

        $trace = StudioTrace::create([
            'id' => (string) Str::uuid(),
            'run_id' => $run->id,
            'thread_id' => $thread->id,
        ]);

        return [$run, $trace];
    }
}
