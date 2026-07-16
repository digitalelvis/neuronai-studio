<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\StudioTrace;
use DigitalElvis\NeuronAIStudio\Models\StudioTraceSpan;
use DigitalElvis\NeuronAIStudio\Runtime\TelemetryTracker;
use Illuminate\Support\Str;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Observability\Events\InferenceStop;

class TelemetryTrackerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'neuronai-studio.usage.pricing.openai.gpt-4o-mini' => [
                'prompt_per_1k' => 0.00015,
                'completion_per_1k' => 0.0006,
            ],
        ]);
    }

    public function test_inference_stop_creates_llm_span_with_provider_model_and_cost(): void
    {
        [$run, $trace] = $this->makeRunAndTrace();

        $tracker = new TelemetryTracker($run, $trace, true, 'openai', 'gpt-4o-mini');

        $response = (new AssistantMessage('hi'))->setUsage(new Usage(1000, 500));
        $tracker->onEvent('inference-stop', new \stdClass, new InferenceStop(
            new UserMessage('hello'),
            $response,
        ));

        $span = StudioTraceSpan::query()->where('trace_id', $trace->id)->where('type', 'llm')->first();

        $this->assertNotNull($span);
        $this->assertSame('openai', $span->provider);
        $this->assertSame('gpt-4o-mini', $span->model);
        $this->assertSame(1000, $span->prompt_tokens);
        $this->assertSame(500, $span->completion_tokens);
        // (1000/1000)*0.00015 + (500/1000)*0.0006 = 0.00015 + 0.0003
        $this->assertSame('0.000450', $span->estimated_cost);

        $run->refresh();
        $this->assertSame(1000, $run->prompt_tokens);
        $this->assertSame(500, $run->completion_tokens);
        $this->assertSame(1500, $run->total_tokens);
        $this->assertSame('0.000450', $run->estimated_cost);
    }

    public function test_inference_stop_increments_parent_run_when_set(): void
    {
        [$parent] = $this->makeRunAndTrace();
        [$child, $trace] = $this->makeRunAndTrace($parent);

        $tracker = new TelemetryTracker($child, $trace, false, 'openai', 'gpt-4o-mini', $parent);

        $response = (new AssistantMessage('hi'))->setUsage(new Usage(1000, 0));
        $tracker->onEvent('inference-stop', new \stdClass, new InferenceStop(false, $response));

        $child->refresh();
        $parent->refresh();

        $this->assertSame(1000, $child->prompt_tokens);
        $this->assertSame('0.000150', $child->estimated_cost);
        $this->assertSame(1000, $parent->prompt_tokens);
        $this->assertSame('0.000150', $parent->estimated_cost);
    }

    public function test_inference_stop_without_usage_still_creates_span(): void
    {
        [$run, $trace] = $this->makeRunAndTrace();

        $tracker = new TelemetryTracker($run, $trace, true, 'openai', 'gpt-4o-mini');
        $tracker->onEvent(
            'inference-stop',
            new \stdClass,
            new InferenceStop(false, new AssistantMessage('no usage')),
        );

        $span = StudioTraceSpan::query()->where('trace_id', $trace->id)->where('type', 'llm')->first();

        $this->assertNotNull($span);
        $this->assertSame(0, $span->total_tokens);
        $this->assertSame('0.000000', $span->estimated_cost);
        $this->assertSame(0, $run->fresh()->total_tokens);
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
