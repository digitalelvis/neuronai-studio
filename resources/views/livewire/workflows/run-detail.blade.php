<div class="studio-product-root flex min-h-0 flex-1 flex-col">
    <script>
        window.__NEURONAI_RUN_DETAIL_CONFIG = {
            run: {
                id: @json($run->id),
                status: @json($run->status),
                workflowName: @json($run->workflow?->name),
                errorMessage: @json($run->error_message),
                input: @json($run->input),
                output: @json($run->output),
            },
            steps: @json($run->steps->map(fn ($step) => [
                'id' => $step->id,
                'node_type' => $step->node_type,
                'node_id' => $step->node_id,
                'duration_ms' => $step->duration_ms,
                'state_snapshot' => $step->state_snapshot,
            ])->values()->all()),
        };
    </script>

    <div id="run-detail-root" class="studio-product-root" wire:ignore></div>
</div>
