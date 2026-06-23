<div class="ab-card">
    <h2>Runs for {{ $workflow->name }}</h2>
    @if ($runs->isEmpty())
        <p class="ab-muted">No runs yet.</p>
    @else
        <table class="ab-table ab-mt">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Started</th>
                    <th>Duration</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($runs as $run)
                    <tr>
                        <td>#{{ $run->id }}</td>
                        <td><span class="ab-badge ab-badge-{{ $run->status }}">{{ $run->status }}</span></td>
                        <td>{{ $run->started_at }}</td>
                        <td>
                            @if ($run->started_at && $run->finished_at)
                                {{ $run->started_at->diffInSeconds($run->finished_at) }}s
                            @endif
                        </td>
                        <td><a href="{{ route('neuronai-studio.workflows.runs.show', $run) }}">Details</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
