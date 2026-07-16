<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Usage;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\StudioTrace;
use DigitalElvis\NeuronAIStudio\Models\StudioTraceSpan;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use DigitalElvis\NeuronAIStudio\Usage\UsageRecorder;
use Illuminate\Support\Str;

class UsageRecorderTest extends TestCase
{
    private UsageRecorder $recorder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recorder = new UsageRecorder;

        config([
            'neuronai-studio.usage.pricing.openai.gpt-4o-mini' => [
                'prompt_per_1k' => 0.00015,
                'completion_per_1k' => 0.0006,
            ],
        ]);
    }

    public function test_record_llm_span_persists_attribution_tokens_and_cost(): void
    {
        [$run, $trace] = $this->makeRunAndTrace();

        // (1000/1000)*0.00015 + (2000/1000)*0.0006 = 0.00015 + 0.0012 = 0.00135
        $span = $this->recorder->recordLlmSpan($run, $trace, 'openai', 'gpt-4o-mini', 1000, 2000);

        $this->assertInstanceOf(StudioTraceSpan::class, $span);
        $this->assertSame('llm', $span->type);
        $this->assertSame('openai', $span->provider);
        $this->assertSame('gpt-4o-mini', $span->model);
        $this->assertSame(1000, $span->prompt_tokens);
        $this->assertSame(2000, $span->completion_tokens);
        $this->assertSame(3000, $span->total_tokens);
        $this->assertSame('0.001350', $span->estimated_cost);

        $run->refresh();
        $this->assertSame(1000, $run->prompt_tokens);
        $this->assertSame(2000, $run->completion_tokens);
        $this->assertSame(3000, $run->total_tokens);
        $this->assertSame('0.001350', $run->estimated_cost);
    }

    public function test_record_llm_span_increments_parent_run_when_provided(): void
    {
        [$parent] = $this->makeRunAndTrace();
        [$child, $trace] = $this->makeRunAndTrace($parent);

        $this->recorder->recordLlmSpan($child, $trace, 'openai', 'gpt-4o-mini', 1000, 0, $parent);

        $child->refresh();
        $parent->refresh();

        $this->assertSame(1000, $child->prompt_tokens);
        $this->assertSame('0.000150', $child->estimated_cost);
        $this->assertSame(1000, $parent->prompt_tokens);
        $this->assertSame('0.000150', $parent->estimated_cost);
        $this->assertSame(1, StudioTraceSpan::where('trace_id', $trace->id)->count());
    }

    public function test_record_llm_span_unpriced_model_writes_zero_cost(): void
    {
        [$run, $trace] = $this->makeRunAndTrace();

        $span = $this->recorder->recordLlmSpan($run, $trace, 'openai', 'no-price', 500, 500);

        $this->assertSame('0.000000', $span->estimated_cost);
        $this->assertSame('0.000000', $run->fresh()->estimated_cost);
        $this->assertSame(1000, $run->fresh()->total_tokens);
    }

    public function test_record_llm_span_null_provider_and_zero_tokens_do_not_throw(): void
    {
        [$run, $trace] = $this->makeRunAndTrace();

        $span = $this->recorder->recordLlmSpan($run, $trace, null, null, 0, 0);

        $this->assertNull($span->provider);
        $this->assertNull($span->model);
        $this->assertSame(0, $span->total_tokens);
        $this->assertSame('0.000000', $span->estimated_cost);
    }

    public function test_finalize_run_sums_own_spans_and_child_runs(): void
    {
        [$parent, $parentTrace] = $this->makeRunAndTrace();
        $this->recorder->recordLlmSpan($parent, $parentTrace, 'openai', 'gpt-4o-mini', 1000, 0);

        [$child, $childTrace] = $this->makeRunAndTrace($parent);
        $this->recorder->recordLlmSpan($child, $childTrace, 'openai', 'gpt-4o-mini', 2000, 500);

        // Simulate interim parent rollup (also done by recordLlmSpan with parent)
        // then overwrite parent tokens to prove finalize recomputes from source.
        $parent->update([
            'prompt_tokens' => 1,
            'completion_tokens' => 1,
            'total_tokens' => 2,
            'estimated_cost' => '0.000001',
        ]);

        $this->recorder->finalizeRun($child->fresh());
        $this->recorder->finalizeRun($parent->fresh());

        $child = $child->fresh();
        $this->assertSame(2000, $child->prompt_tokens);
        $this->assertSame(500, $child->completion_tokens);
        $this->assertSame(2500, $child->total_tokens);
        $this->assertSame('0.000600', $child->estimated_cost);

        $parent = $parent->fresh();
        // own 1000/0 + child 2000/500
        $this->assertSame(3000, $parent->prompt_tokens);
        $this->assertSame(500, $parent->completion_tokens);
        $this->assertSame(3500, $parent->total_tokens);
        // 0.000150 + 0.000600 = 0.000750
        $this->assertSame('0.000750', $parent->estimated_cost);
    }

    /**
     * @return array{0: StudioRun, 1: StudioTrace}
     */
    private function makeRunAndTrace(?StudioRun $parent = null): array
    {
        $thread = StudioThread::create([
            'id' => (string) Str::uuid(),
        ]);

        $run = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'parent_run_id' => $parent?->id,
            'status' => 'running',
        ]);

        $trace = StudioTrace::create([
            'id' => (string) Str::uuid(),
            'run_id' => $run->id,
        ]);

        return [$run, $trace];
    }
}
