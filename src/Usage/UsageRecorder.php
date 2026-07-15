<?php

namespace DigitalElvis\NeuronAIStudio\Usage;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioTrace;
use DigitalElvis\NeuronAIStudio\Models\StudioTraceSpan;

class UsageRecorder
{
    public function __construct(
        protected UsageCostEstimator $estimator = new UsageCostEstimator,
    ) {}

    /**
     * Persist one LLM span and roll tokens/cost into the run (and optional parent).
     *
     * Missing pricing or zero/null usage never throws — cost stays 0.
     */
    public function recordLlmSpan(
        StudioRun $run,
        StudioTrace $trace,
        ?string $provider,
        ?string $model,
        int $promptTokens,
        int $completionTokens,
        ?StudioRun $parentRun = null,
        ?string $parentSpanId = null,
        ?array $input = null,
        ?array $output = null,
    ): StudioTraceSpan {
        $promptTokens = max(0, $promptTokens);
        $completionTokens = max(0, $completionTokens);
        $totalTokens = $promptTokens + $completionTokens;
        $estimatedCost = $this->estimator->estimate(
            $provider,
            $model,
            $promptTokens,
            $completionTokens,
        );

        $span = StudioTraceSpan::create([
            'trace_id' => $trace->id,
            'parent_span_id' => $parentSpanId,
            'name' => 'llm_inference',
            'type' => 'llm',
            'status' => 'completed',
            'provider' => $provider,
            'model' => $model,
            'input' => $input,
            'output' => $output,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'estimated_cost' => $estimatedCost,
            'started_at' => now(),
            'finished_at' => now(),
            'duration_ms' => 0,
        ]);

        $this->incrementRunUsage($run, $promptTokens, $completionTokens, $totalTokens, $estimatedCost);

        if ($parentRun !== null) {
            $this->incrementRunUsage($parentRun, $promptTokens, $completionTokens, $totalTokens, $estimatedCost);
        }

        return $span;
    }

    protected function incrementRunUsage(
        StudioRun $run,
        int $promptTokens,
        int $completionTokens,
        int $totalTokens,
        string $estimatedCost,
    ): void {
        $run->increment('prompt_tokens', $promptTokens);
        $run->increment('completion_tokens', $completionTokens);
        $run->increment('total_tokens', $totalTokens);
        $run->increment('estimated_cost', (float) $estimatedCost);
    }
}
