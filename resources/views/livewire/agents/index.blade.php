<div>
    <div class="ab-toolbar">
        <a href="{{ route('neuronai-studio.agents.create') }}" class="ab-btn ab-btn-primary">New Agent</a>
    </div>

    <div class="ab-card ab-mt">
        @if ($agents->isEmpty())
            <p class="ab-muted">No agents yet. Create your first agent to get started.</p>
        @else
            <table class="ab-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Provider</th>
                        <th>Model</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($agents as $agent)
                        <tr>
                            <td>
                                <strong>{{ $agent->name }}</strong>
                                @if ($agent->description)
                                    <div class="ab-muted">{{ \Illuminate\Support\Str::limit($agent->description, 60) }}</div>
                                @endif
                            </td>
                            <td>{{ $agent->provider }}</td>
                            <td>{{ $agent->model }}</td>
                            <td class="ab-actions">
                                <a href="{{ route('neuronai-studio.agents.playground', $agent) }}">Playground</a>
                                <a href="{{ route('neuronai-studio.agents.edit', $agent) }}">Edit</a>
                                <button wire:click="delete({{ $agent->id }})" wire:confirm="Delete this agent?" class="ab-btn-link ab-danger">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
