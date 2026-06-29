<x-neuronai-studio::ui.page>
    <x-neuronai-studio::ui.card>
        @if ($runs->isEmpty())
            <x-neuronai-studio::ui.empty-state title="No runs yet" description="Run this suite from the editor to see results here." />
        @else
            <x-neuronai-studio::ui.table>
                <x-neuronai-studio::ui.table-head>
                    <tr>
                        <x-neuronai-studio::ui.table-header>Run</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Status</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Passed</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Success Rate</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Duration</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header></x-neuronai-studio::ui.table-header>
                    </tr>
                </x-neuronai-studio::ui.table-head>
                <x-neuronai-studio::ui.table-body>
                    @foreach ($runs as $run)
                        <x-neuronai-studio::ui.table-row wire:key="eval-run-{{ $run->id }}">
                            <x-neuronai-studio::ui.table-cell>#{{ $run->id }}</x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.badge variant="{{ str_contains($run->status, 'fail') ? 'destructive' : 'secondary' }}">{{ $run->status }}</x-neuronai-studio::ui.badge>
                            </x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>{{ $run->passed_count }} / {{ $run->passed_count + $run->failed_count }}</x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>{{ number_format($run->success_rate * 100, 1) }}%</x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>{{ $run->total_time_ms }}ms</x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.button variant="ghost" size="sm" :href="route('neuronai-studio.agents.eval-runs.show', $run)">Details</x-neuronai-studio::ui.button>
                            </x-neuronai-studio::ui.table-cell>
                        </x-neuronai-studio::ui.table-row>
                    @endforeach
                </x-neuronai-studio::ui.table-body>
            </x-neuronai-studio::ui.table>
        @endif
    </x-neuronai-studio::ui.card>
</x-neuronai-studio::ui.page>
