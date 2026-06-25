<x-neuronai-studio::ui.page>
    <x-neuronai-studio::ui.card>
        @if ($traces->isEmpty())
            <x-neuronai-studio::ui.empty-state title="No traces yet" description="Test the workflow from the editor to create a trace." />
        @else
            <x-neuronai-studio::ui.table>
                <x-neuronai-studio::ui.table-head>
                    <tr>
                        <x-neuronai-studio::ui.table-header>ID</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Status</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Started</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header></x-neuronai-studio::ui.table-header>
                    </tr>
                </x-neuronai-studio::ui.table-head>
                <x-neuronai-studio::ui.table-body>
                    @foreach ($traces as $trace)
                        <x-neuronai-studio::ui.table-row wire:key="trace-{{ $trace->id }}">
                            <x-neuronai-studio::ui.table-cell>#{{ $trace->id }}</x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.badge :variant="$trace->status">{{ $trace->status }}</x-neuronai-studio::ui.badge>
                            </x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell class="text-muted-foreground">{{ $trace->started_at?->diffForHumans() }}</x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.button variant="ghost" size="sm" :href="route('neuronai-studio.workflows.traces.show', $trace)">Details</x-neuronai-studio::ui.button>
                            </x-neuronai-studio::ui.table-cell>
                        </x-neuronai-studio::ui.table-row>
                    @endforeach
                </x-neuronai-studio::ui.table-body>
            </x-neuronai-studio::ui.table>
        @endif
    </x-neuronai-studio::ui.card>
</x-neuronai-studio::ui.page>
