<x-neuronai-studio::ui.page>
    <x-neuronai-studio::ui.card class="mb-4">
        <x-neuronai-studio::ui.card-content class="pt-4">
            <x-neuronai-studio::ui.form-group>
                <x-neuronai-studio::ui.label>Filter</x-neuronai-studio::ui.label>
                <x-neuronai-studio::ui.input type="text" wire:model.live="filter" placeholder="Search by name, reference or category..." />
            </x-neuronai-studio::ui.form-group>
        </x-neuronai-studio::ui.card-content>
    </x-neuronai-studio::ui.card>

    <x-neuronai-studio::ui.card>
        @if ($tools->isEmpty())
            <x-neuronai-studio::ui.empty-state title="No tools found" />
        @else
            <x-neuronai-studio::ui.table>
                <x-neuronai-studio::ui.table-head>
                    <tr>
                        <x-neuronai-studio::ui.table-header>Name</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Category</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Reference</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Type</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header></x-neuronai-studio::ui.table-header>
                    </tr>
                </x-neuronai-studio::ui.table-head>
                <x-neuronai-studio::ui.table-body>
                    @foreach ($tools as $tool)
                        <x-neuronai-studio::ui.table-row wire:key="tool-{{ $tool['ref'] }}">
                            <x-neuronai-studio::ui.table-cell>
                                <strong>{{ $tool['label'] }}</strong>
                                @if ($tool['description'])
                                    <div class="text-sm text-muted-foreground">{{ \Illuminate\Support\Str::limit($tool['description'], 80) }}</div>
                                @endif
                            </x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.badge variant="secondary">{{ $categoryLabels[$tool['category']] ?? $tool['category'] }}</x-neuronai-studio::ui.badge>
                            </x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell><code class="text-xs">{{ $tool['ref'] }}</code></x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>{{ $tool['type'] }}</x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>
                                <div class="studio-table-row-actions">
                                    @if (str_starts_with($tool['ref'], 'tool:db:'))
                                        @php($id = (int) \Illuminate\Support\Str::after($tool['ref'], 'tool:db:'))
                                        <x-neuronai-studio::ui.button variant="ghost" size="sm" :href="route('neuronai-studio.tools.show', $id)">View</x-neuronai-studio::ui.button>
                                        <x-neuronai-studio::ui.button variant="ghost" size="sm" :href="route('neuronai-studio.tools.edit', $id)">Edit</x-neuronai-studio::ui.button>
                                        <x-neuronai-studio::ui.button variant="ghost" size="sm" wire:click="delete({{ $id }})" wire:confirm="Delete this tool?" class="text-destructive">Delete</x-neuronai-studio::ui.button>
                                    @elseif (str_starts_with($tool['ref'], 'class:'))
                                        <x-neuronai-studio::ui.button variant="ghost" size="sm" :href="route('neuronai-studio.tools.registry', ['ref' => $tool['ref']])">View</x-neuronai-studio::ui.button>
                                        <x-neuronai-studio::ui.button variant="ghost" size="sm" :href="route('neuronai-studio.tools.create', ['import' => \Illuminate\Support\Str::after($tool['ref'], 'class:')])">Edit</x-neuronai-studio::ui.button>
                                    @else
                                        <x-neuronai-studio::ui.button variant="ghost" size="sm" :href="route('neuronai-studio.tools.registry', ['ref' => $tool['ref']])">View</x-neuronai-studio::ui.button>
                                    @endif
                                </div>
                            </x-neuronai-studio::ui.table-cell>
                        </x-neuronai-studio::ui.table-row>
                    @endforeach
                </x-neuronai-studio::ui.table-body>
            </x-neuronai-studio::ui.table>
        @endif
    </x-neuronai-studio::ui.card>
</x-neuronai-studio::ui.page>
