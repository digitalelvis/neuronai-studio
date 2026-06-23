<div>
    <div class="ab-grid ab-grid-3">
        <div class="ab-card">
            <div class="ab-stat-label">Agents</div>
            <div class="ab-stat-value">{{ $agentCount }}</div>
        </div>
        <div class="ab-card">
            <div class="ab-stat-label">Workflows</div>
            <div class="ab-stat-value">{{ $workflowCount }}</div>
        </div>
        <div class="ab-card">
            <div class="ab-stat-label">Recent Runs</div>
            <div class="ab-stat-value">{{ $recentRuns->count() }}</div>
        </div>
    </div>

    <div class="ab-card ab-mt">
        <h2>Recent Workflow Runs</h2>
        @if ($recentRuns->isEmpty())
            <p class="ab-muted">No workflow runs yet.</p>
        @else
            <table class="ab-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Workflow</th>
                        <th>Status</th>
                        <th>Started</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentRuns as $run)
                        <tr>
                            <td>#{{ $run->id }}</td>
                            <td>{{ $run->workflow?->name }}</td>
                            <td><span class="ab-badge ab-badge-{{ $run->status }}">{{ $run->status }}</span></td>
                            <td>{{ $run->started_at?->diffForHumans() }}</td>
                            <td><a href="{{ route('neuronai-studio.workflows.runs.show', $run) }}">View</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
