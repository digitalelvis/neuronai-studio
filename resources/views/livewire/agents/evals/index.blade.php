<x-neuronai-studio::ui.page>
    <x-neuronai-studio::ui.card>
        @if ($suites->isEmpty())
            <x-neuronai-studio::ui.empty-state title="No eval suites yet" description="Create a suite to test your agent with datasets and assertions.">
                <x-neuronai-studio::ui.button :href="route('neuronai-studio.agents.evals.create', $agent)">New Eval Suite</x-neuronai-studio::ui.button>
            </x-neuronai-studio::ui.empty-state>
        @else
            <x-neuronai-studio::ui.table>
                <x-neuronai-studio::ui.table-head>
                    <tr>
                        <x-neuronai-studio::ui.table-header>Name</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Cases</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Judge</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Last Updated</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header></x-neuronai-studio::ui.table-header>
                    </tr>
                </x-neuronai-studio::ui.table-head>
                <x-neuronai-studio::ui.table-body>
                    @foreach ($suites as $suite)
                        <x-neuronai-studio::ui.table-row wire:key="eval-suite-{{ $suite->id }}">
                            <x-neuronai-studio::ui.table-cell>
                                <strong>{{ $suite->name }}</strong>
                                <div class="text-sm text-muted-foreground"><code>{{ $suite->slug }}</code></div>
                            </x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>{{ count($suite->dataset ?? []) }}</x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>
                                @if ($suite->judgeAgent)
                                    <x-neuronai-studio::ui.badge variant="secondary">{{ $suite->judgeAgent->name }}</x-neuronai-studio::ui.badge>
                                @else
                                    <span class="text-sm text-muted-foreground">—</span>
                                @endif
                            </x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>{{ $suite->updated_at?->diffForHumans() }}</x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>
                                <div class="studio-table-row-actions">
                                    <x-neuronai-studio::ui.button variant="ghost" size="sm" :href="route('neuronai-studio.agents.evals.edit', ['agent' => $agent, 'suite' => $suite])">Edit</x-neuronai-studio::ui.button>
                                    <x-neuronai-studio::ui.button variant="ghost" size="sm" :href="route('neuronai-studio.agents.evals.runs', ['agent' => $agent, 'suite' => $suite])">Runs</x-neuronai-studio::ui.button>
                                    <x-neuronai-studio::ui.button variant="ghost" size="sm" wire:click="delete({{ $suite->id }})" wire:confirm="Delete this eval suite?" class="text-destructive">Delete</x-neuronai-studio::ui.button>
                                </div>
                            </x-neuronai-studio::ui.table-cell>
                        </x-neuronai-studio::ui.table-row>
                    @endforeach
                </x-neuronai-studio::ui.table-body>
            </x-neuronai-studio::ui.table>
        @endif
    </x-neuronai-studio::ui.card>
</x-neuronai-studio::ui.page>
