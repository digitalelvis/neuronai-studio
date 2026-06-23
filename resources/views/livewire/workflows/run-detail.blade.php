<div class="ab-grid ab-grid-2">
    <div class="ab-card">
        <h2>Run #{{ $run->id }}</h2>
        <p><strong>Workflow:</strong> {{ $run->workflow?->name }}</p>
        <p><strong>Status:</strong> <span class="ab-badge ab-badge-{{ $run->status }}">{{ $run->status }}</span></p>
        @if ($run->error_message)
            <p class="ab-error">{{ $run->error_message }}</p>
        @endif
        <h3 class="ab-mt">Input</h3>
        <pre class="ab-code">{{ json_encode($run->input, JSON_PRETTY_PRINT) }}</pre>
        <h3 class="ab-mt">Output</h3>
        <pre class="ab-code">{{ json_encode($run->output, JSON_PRETTY_PRINT) }}</pre>
    </div>
    <div class="ab-card">
        <h3>Execution Steps</h3>
        @forelse ($run->steps as $step)
            <div class="ab-step">
                <div class="ab-step-header">
                    <strong>{{ $step->node_type }}</strong>
                    <span class="ab-muted">{{ $step->node_id }}</span>
                    @if ($step->duration_ms)
                        <span class="ab-muted">{{ $step->duration_ms }}ms</span>
                    @endif
                </div>
                @if ($step->state_snapshot)
                    <pre class="ab-code ab-code-sm">{{ json_encode($step->state_snapshot, JSON_PRETTY_PRINT) }}</pre>
                @endif
            </div>
        @empty
            <p class="ab-muted">No steps recorded.</p>
        @endforelse
    </div>
</div>
