<x-neuronai-studio::ui.page>
    <x-neuronai-studio::ui.card class="mb-4">
        <x-neuronai-studio::ui.card-content class="pt-4">
            <x-neuronai-studio::ui.form-group>
                <x-neuronai-studio::ui.label>Filter</x-neuronai-studio::ui.label>
                <x-neuronai-studio::ui.input type="text" wire:model.live="filter" placeholder="Search by name or slug..." />
            </x-neuronai-studio::ui.form-group>
        </x-neuronai-studio::ui.card-content>
    </x-neuronai-studio::ui.card>

    <x-neuronai-studio::ui.card>
        @if ($knowledgeBases->isEmpty())
            <x-neuronai-studio::ui.empty-state title="No knowledge bases yet" description="Create a knowledge base and ingest documents to power RAG nodes.">
                <x-neuronai-studio::ui.button :href="route('neuronai-studio.knowledge-bases.create')">New Knowledge Base</x-neuronai-studio::ui.button>
            </x-neuronai-studio::ui.empty-state>
        @else
            <x-neuronai-studio::ui.table>
                <x-neuronai-studio::ui.table-head>
                    <tr>
                        <x-neuronai-studio::ui.table-header>Name</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Embeddings</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Vector Store</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header>Documents</x-neuronai-studio::ui.table-header>
                        <x-neuronai-studio::ui.table-header></x-neuronai-studio::ui.table-header>
                    </tr>
                </x-neuronai-studio::ui.table-head>
                <x-neuronai-studio::ui.table-body>
                    @foreach ($knowledgeBases as $knowledgeBase)
                        <x-neuronai-studio::ui.table-row wire:key="kb-{{ $knowledgeBase->id }}">
                            <x-neuronai-studio::ui.table-cell>
                                <strong>{{ $knowledgeBase->name }}</strong>
                                @if ($knowledgeBase->description)
                                    <div class="text-sm text-muted-foreground">{{ \Illuminate\Support\Str::limit($knowledgeBase->description, 60) }}</div>
                                @endif
                                <div class="text-xs text-muted-foreground"><code>{{ $knowledgeBase->slug }}</code></div>
                            </x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>
                                {{ $knowledgeBase->embeddingsProvider() }}
                                <div class="text-xs text-muted-foreground">{{ $knowledgeBase->embeddingsModel() }}</div>
                            </x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>{{ $knowledgeBase->vectorStoreDriver() }}</x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.badge variant="secondary">{{ $knowledgeBase->documents_count }}</x-neuronai-studio::ui.badge>
                            </x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.table-cell>
                                <div class="studio-table-row-actions">
                                    <x-neuronai-studio::ui.button variant="ghost" size="sm" :href="route('neuronai-studio.knowledge-bases.edit', $knowledgeBase)">Edit</x-neuronai-studio::ui.button>
                                    <x-neuronai-studio::ui.button variant="ghost" size="sm" wire:click="delete({{ $knowledgeBase->id }})" wire:confirm="Delete this knowledge base and its documents?" class="text-destructive hover:text-destructive">Delete</x-neuronai-studio::ui.button>
                                </div>
                            </x-neuronai-studio::ui.table-cell>
                        </x-neuronai-studio::ui.table-row>
                    @endforeach
                </x-neuronai-studio::ui.table-body>
            </x-neuronai-studio::ui.table>
        @endif
    </x-neuronai-studio::ui.card>
</x-neuronai-studio::ui.page>
