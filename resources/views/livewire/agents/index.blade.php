<x-neuronai-studio::ui.page>
    <x-neuronai-studio::ui.card>
        @if ($agents->isEmpty())
            <x-neuronai-studio::ui.empty-state title="No agents yet" description="Create your first agent to get started.">
                <x-neuronai-studio::ui.button :href="route('neuronai-studio.agents.create')">New Agent</x-neuronai-studio::ui.button>
            </x-neuronai-studio::ui.empty-state>
        @else
            <x-neuronai-studio::ui.table>
                <x-neuronai-studio::ui.table-head>
                    <tr>
                        <x-neuronai-studio::ui.table-header>Name</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Provider</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Model</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header></x-neuronai-studio::ui.table-header>
                    </tr>
                </x-neuronai-studio::ui.table-head>
                <x-neuronai-studio::ui.table-body>
                    @foreach ($agents as $agent)
                        <x-neuronai-studio::ui.table-row wire:key="agent-{{ $agent->id }}">
                            <x-neuronai-studio::ui.table-cell>
                                <strong>{{ $agent->name }}</strong>
                                @if ($agent->description)
                                    <div class="text-sm text-muted-foreground">{{ \Illuminate\Support\Str::limit($agent->description, 60) }}</div>
                                @endif
                            </x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>{{ $agent->provider }}</x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>{{ $agent->model }}</x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>
                                <div class="studio-table-row-actions">
                                    <x-neuronai-studio::ui.button variant="ghost" size="sm" :href="route('neuronai-studio.agents.playground', $agent)">Playground</x-neuronai-studio::ui.button>
                                    <x-neuronai-studio::ui.button variant="ghost" size="sm" :href="route('neuronai-studio.agents.edit', $agent)">Edit</x-neuronai-studio::ui.button>
                                    <x-neuronai-studio::ui.button variant="ghost" size="sm" wire:click="delete({{ $agent->id }})" wire:confirm="Delete this agent?" class="text-destructive hover:text-destructive">Delete</x-neuronai-studio::ui.button>
                                </div>
                            </x-neuronai-studio::ui.table-cell>
                        </x-neuronai-studio::ui.table-row>
                    @endforeach
                </x-neuronai-studio::ui.table-body>
            </x-neuronai-studio::ui.table>
        @endif
    </x-neuronai-studio::ui.card>
</x-neuronai-studio::ui.page>
