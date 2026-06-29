<x-neuronai-studio::ui.page>
    <x-neuronai-studio::ui.card class="mb-4">
        <x-neuronai-studio::ui.card-header>
            <h2 class="text-base font-semibold">Studio Workflows</h2>
        </x-neuronai-studio::ui.card-header>
        <x-neuronai-studio::ui.card-content>
            @if ($workflows->isEmpty())
                <x-neuronai-studio::ui.empty-state title="No studio workflows yet" />
            @else
                <x-neuronai-studio::ui.table>
                    <x-neuronai-studio::ui.table-head>
                        <tr>
                            <x-neuronai-studio::ui.table-header>Name</x-neuronai-studio::ui.table-header>
                            <x-neuronai-studio::ui.table-header>Source</x-neuronai-studio::ui.table-header>
                            <x-neuronai-studio::ui.table-header>Status</x-neuronai-studio::ui.table-header>
                            <x-neuronai-studio::ui.table-header>Updated</x-neuronai-studio::ui.table-header>
                            <x-neuronai-studio::ui.table-header></x-neuronai-studio::ui.table-header>
                        </tr>
                    </x-neuronai-studio::ui.table-head>
                    <x-neuronai-studio::ui.table-body>
                        @foreach ($workflows as $workflow)
                            <x-neuronai-studio::ui.table-row wire:key="workflow-studio-{{ $workflow->id }}">
                                <x-neuronai-studio::ui.table-cell><strong>{{ $workflow->name }}</strong></x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.table-cell><x-neuronai-studio::ui.badge variant="secondary">Studio</x-neuronai-studio::ui.badge></x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.table-cell><x-neuronai-studio::ui.badge :variant="$workflow->status">{{ $workflow->status }}</x-neuronai-studio::ui.badge></x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.table-cell class="text-muted-foreground">{{ $workflow->updated_at->diffForHumans() }}</x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.table-cell>
                                    <div class="studio-table-row-actions">
                                        <x-neuronai-studio::ui.button variant="ghost" size="sm" :href="route('neuronai-studio.workflows.edit', $workflow)">Edit</x-neuronai-studio::ui.button>
                                        <x-neuronai-studio::ui.button variant="ghost" size="sm" :href="route('neuronai-studio.workflows.traces', $workflow)">Traces</x-neuronai-studio::ui.button>
                                        <x-neuronai-studio::ui.button variant="ghost" size="sm" wire:click="delete({{ $workflow->id }})" wire:confirm="Delete this workflow?" class="text-destructive">Delete</x-neuronai-studio::ui.button>
                                    </div>
                                </x-neuronai-studio::ui.table-cell>
                            </x-neuronai-studio::ui.table-row>
                        @endforeach
                    </x-neuronai-studio::ui.table-body>
                </x-neuronai-studio::ui.table>
            @endif
        </x-neuronai-studio::ui.card-content>
    </x-neuronai-studio::ui.card>

    <x-neuronai-studio::ui.card>
        <x-neuronai-studio::ui.card-header>
            <h2 class="text-base font-semibold">Code &amp; JSON Workflows</h2>
        </x-neuronai-studio::ui.card-header>
        <x-neuronai-studio::ui.card-content>
            @if ($codeWorkflows === [])
                <x-neuronai-studio::ui.empty-state title="No code or JSON workflows discovered" description="Export a workflow as PHP or add files to configured scan paths." />
            @else
                <x-neuronai-studio::ui.table>
                    <x-neuronai-studio::ui.table-head>
                        <tr>
                            <x-neuronai-studio::ui.table-header>Name</x-neuronai-studio::ui.table-header>
                            <x-neuronai-studio::ui.table-header>Source</x-neuronai-studio::ui.table-header>
                            <x-neuronai-studio::ui.table-header>Reference</x-neuronai-studio::ui.table-header>
                            <x-neuronai-studio::ui.table-header></x-neuronai-studio::ui.table-header>
                        </tr>
                    </x-neuronai-studio::ui.table-head>
                    <x-neuronai-studio::ui.table-body>
                        @foreach ($codeWorkflows as $entry)
                            <x-neuronai-studio::ui.table-row wire:key="workflow-code-{{ $entry['ref'] }}">
                                <x-neuronai-studio::ui.table-cell>
                                    <strong>{{ $entry['label'] }}</strong>
                                    @if ($entry['description'])
                                        <div class="text-sm text-muted-foreground">{{ \Illuminate\Support\Str::limit($entry['description'], 80) }}</div>
                                    @endif
                                </x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.table-cell>
                                    <x-neuronai-studio::ui.badge variant="outline">{{ $entry['source'] === 'json' ? 'JSON' : 'Code' }}</x-neuronai-studio::ui.badge>
                                </x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.table-cell><code class="text-xs">{{ $entry['class_path'] ?? $entry['json_path'] }}</code></x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.table-cell>
                                    <div class="studio-table-row-actions">
                                        @if ($entry['class_path'])
                                            <x-neuronai-studio::ui.button variant="ghost" size="sm" :href="route('neuronai-studio.workflows.preview', ['class' => $entry['class_path']])">Preview</x-neuronai-studio::ui.button>
                                        @elseif ($entry['json_path'])
                                            <x-neuronai-studio::ui.button variant="ghost" size="sm" :href="route('neuronai-studio.workflows.preview', ['json' => $entry['json_path']])">Preview</x-neuronai-studio::ui.button>
                                        @endif
                                        <x-neuronai-studio::ui.button variant="ghost" size="sm" wire:click="importToStudio('{{ $entry['ref'] }}')">Import to Studio</x-neuronai-studio::ui.button>
                                    </div>
                                </x-neuronai-studio::ui.table-cell>
                            </x-neuronai-studio::ui.table-row>
                        @endforeach
                    </x-neuronai-studio::ui.table-body>
                </x-neuronai-studio::ui.table>
            @endif
        </x-neuronai-studio::ui.card-content>
    </x-neuronai-studio::ui.card>
</x-neuronai-studio::ui.page>
