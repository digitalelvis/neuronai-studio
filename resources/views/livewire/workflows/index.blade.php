<div>
    <div class="ab-toolbar">
        <a href="{{ route('neuronai-studio.workflows.create') }}" class="ab-btn ab-btn-primary">New Workflow</a>
    </div>

    <div class="ab-card ab-mt">
        @if ($workflows->isEmpty())
            <p class="ab-muted">No workflows yet.</p>
        @else
            <table class="ab-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($workflows as $workflow)
                        <tr>
                            <td><strong>{{ $workflow->name }}</strong></td>
                            <td><span class="ab-badge">{{ $workflow->status }}</span></td>
                            <td>{{ $workflow->updated_at->diffForHumans() }}</td>
                            <td class="ab-actions">
                                <a href="{{ route('neuronai-studio.workflows.edit', $workflow) }}">Edit</a>
                                <a href="{{ route('neuronai-studio.workflows.runs', $workflow) }}">Runs</a>
                                <button wire:click="delete({{ $workflow->id }})" wire:confirm="Delete this workflow?" class="ab-btn-link ab-danger">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
