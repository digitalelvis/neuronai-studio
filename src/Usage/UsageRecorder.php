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

    /**
     * Recompute run token/cost totals as own spans + child runs.
     * Overwrites interim increment rollups so parent finalize cannot zero nested usage.
     */
    public function finalizeRun(StudioRun $run): void
    {
        $own = StudioTraceSpan::query()
            ->whereHas('trace', fn ($query) => $query->where('run_id', $run->id))
            ->toBase()
            ->selectRaw('COALESCE(SUM(prompt_tokens), 0) as prompt_tokens')
            ->selectRaw('COALESCE(SUM(completion_tokens), 0) as completion_tokens')
            ->selectRaw('COALESCE(SUM(total_tokens), 0) as total_tokens')
            ->selectRaw('COALESCE(SUM(estimated_cost), 0) as estimated_cost')
            ->first();

        $children = StudioRun::query()
            ->where('parent_run_id', $run->id)
            ->toBase()
            ->selectRaw('COALESCE(SUM(prompt_tokens), 0) as prompt_tokens')
            ->selectRaw('COALESCE(SUM(completion_tokens), 0) as completion_tokens')
            ->selectRaw('COALESCE(SUM(total_tokens), 0) as total_tokens')
            ->selectRaw('COALESCE(SUM(estimated_cost), 0) as estimated_cost')
            ->first();

        $promptTokens = (int) ($own->prompt_tokens ?? 0) + (int) ($children->prompt_tokens ?? 0);
        $completionTokens = (int) ($own->completion_tokens ?? 0) + (int) ($children->completion_tokens ?? 0);
        $totalTokens = (int) ($own->total_tokens ?? 0) + (int) ($children->total_tokens ?? 0);
        $estimatedCost = number_format(
            (float) ($own->estimated_cost ?? 0) + (float) ($children->estimated_cost ?? 0),
            6,
            '.',
            '',
        );

        $run->update([
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'estimated_cost' => $estimatedCost,
        ]);
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
