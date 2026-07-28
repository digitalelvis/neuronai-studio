<x-neuronai-studio::ui.page>
    <x-neuronai-studio::ui.card>
        @if ($suites->isEmpty())
            <x-neuronai-studio::ui.empty-state :title="__('neuronai-studio::ui.empty.evals_title')" :description="__('neuronai-studio::ui.empty.evals_description')">
                <x-neuronai-studio::ui.button :href="route('neuronai-studio.agents.evals.create', $agent)">{{ __('neuronai-studio::ui.actions.new_eval_suite') }}</x-neuronai-studio::ui.button>
            </x-neuronai-studio::ui.empty-state>
        @else
            <x-neuronai-studio::ui.table>
                <x-neuronai-studio::ui.table-head>
                    <tr>
                        <x-neuronai-studio::ui.table-header>{{ __('neuronai-studio::ui.table.name') }}</x-neuronai-studio::ui.table-header>
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
                                    <x-neuronai-studio::ui.button variant="ghost" size="sm" :href="route('neuronai-studio.agents.evals.edit', ['agent' => $agent, 'suite' => $suite])">{{ __('neuronai-studio::ui.actions.edit') }}</x-neuronai-studio::ui.button>
                                    <x-neuronai-studio::ui.button variant="ghost" size="sm" :href="route('neuronai-studio.agents.evals.runs', ['agent' => $agent, 'suite' => $suite])">{{ __('neuronai-studio::ui.breadcrumbs.runs') }}</x-neuronai-studio::ui.button>
                                    <x-neuronai-studio::ui.button variant="ghost" size="sm" wire:click="delete({{ $suite->id }})" wire:confirm="{{ __('neuronai-studio::ui.confirm.delete_eval_suite') }}" class="text-destructive">{{ __('neuronai-studio::ui.actions.delete') }}</x-neuronai-studio::ui.button>
                                </div>
                            </x-neuronai-studio::ui.table-cell>
                        </x-neuronai-studio::ui.table-row>
                    @endforeach
                </x-neuronai-studio::ui.table-body>
            </x-neuronai-studio::ui.table>
        @endif
    </x-neuronai-studio::ui.card>
</x-neuronai-studio::ui.page>
