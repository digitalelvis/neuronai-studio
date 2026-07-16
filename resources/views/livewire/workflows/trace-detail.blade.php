@php
    $stepsForDetail = $trace->steps->map(fn ($step) => [
        'id' => $step->id,
        'node_type' => $step->node_type,
        'node_id' => $step->node_id,
        'duration_ms' => $step->duration_ms,
        'state_snapshot' => $step->state_snapshot,
        'prompt_tokens' => $step->prompt_tokens,
        'completion_tokens' => $step->completion_tokens,
        'total_tokens' => $step->total_tokens,
        'estimated_cost' => $step->estimated_cost,
        'provider' => $step->provider,
        'model' => $step->model,
    ])->values()->all();
@endphp

<div class="studio-product-root flex min-h-0 flex-1 flex-col">
    <script>
        window.__NEURONAI_TRACE_DETAIL_CONFIG = {
            trace: {
                id: @json($trace->id),
                status: @json($trace->status),
                workflowName: @json($trace->workflow?->name),
                errorMessage: @json($trace->error_message),
                input: @json($trace->input),
                output: @json($trace->output),
                startedAt: @json($trace->started_at?->toIso8601String()),
                finishedAt: @json($trace->finished_at?->toIso8601String()),
                durationMs: @json($trace->durationMs()),
                promptTokens: @json($trace->prompt_tokens),
                completionTokens: @json($trace->completion_tokens),
                totalTokens: @json($trace->total_tokens),
                estimatedCost: @json($trace->estimated_cost),
                currency: @json(config('neuronai-studio.usage.currency', 'USD')),
            },
            steps: @json($stepsForDetail),
            traceShowUrl: @json(route('neuronai-studio.workflows.traces.show', $trace)),
        };
    </script>

    <div id="trace-detail-root" class="studio-product-root min-h-0 flex-1" wire:ignore></div>
</div>
