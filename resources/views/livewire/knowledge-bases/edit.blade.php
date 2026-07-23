@php
    $statusVariants = [
        'completed' => 'completed',
        'failed' => 'failed',
        'processing' => 'running',
        'pending' => 'draft',
    ];
@endphp

<x-neuronai-studio::ui.page>
    <form wire:submit="save">
        <x-neuronai-studio::ui.card>
            <x-neuronai-studio::ui.card-content class="space-y-4 pt-4">
                <x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.label>Name</x-neuronai-studio::ui.label>
                    <x-neuronai-studio::ui.input type="text" wire:model="name" required />
                    @error('name') <x-neuronai-studio::ui.form-error>{{ $message }}</x-neuronai-studio::ui.form-error> @enderror
                </x-neuronai-studio::ui.form-group>

                <x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.label>Description</x-neuronai-studio::ui.label>
                    <x-neuronai-studio::ui.textarea wire:model="description" rows="2"></x-neuronai-studio::ui.textarea>
                </x-neuronai-studio::ui.form-group>

                <x-neuronai-studio::ui.form-row>
                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>Embeddings Provider</x-neuronai-studio::ui.label>
                        <x-neuronai-studio::ui.select wire:model.live="embeddingsProvider">
                            @foreach ($providers as $key => $provider)
                                <option value="{{ $key }}">{{ $provider['label'] ?? $key }}</option>
                            @endforeach
                        </x-neuronai-studio::ui.select>
                        @error('embeddingsProvider') <x-neuronai-studio::ui.form-error>{{ $message }}</x-neuronai-studio::ui.form-error> @enderror
                    </x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>Embeddings Model</x-neuronai-studio::ui.label>
                        <x-neuronai-studio::ui.select wire:model="embeddingsModel">
                            @foreach ($models as $model)
                                <option value="{{ $model }}">{{ $model }}</option>
                            @endforeach
                        </x-neuronai-studio::ui.select>
                        <p class="mt-1 text-xs text-muted-foreground">Uses the provider default when left blank.</p>
                    </x-neuronai-studio::ui.form-group>
                </x-neuronai-studio::ui.form-row>

                <x-neuronai-studio::ui.form-row>
                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>Vector Store</x-neuronai-studio::ui.label>
                        <x-neuronai-studio::ui.select wire:model.live="vectorStoreDriver">
                            @foreach ($vectorStores as $key => $store)
                                <option value="{{ $key }}">{{ $store['label'] ?? $key }}</option>
                            @endforeach
                        </x-neuronai-studio::ui.select>
                        @if ($vectorStoreDescription !== '')
                            <p class="mt-1 text-xs text-muted-foreground">{{ $vectorStoreDescription }}</p>
                        @endif
                        @error('vectorStoreDriver') <x-neuronai-studio::ui.form-error>{{ $message }}</x-neuronai-studio::ui.form-error> @enderror
                    </x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>Top K</x-neuronai-studio::ui.label>
                        <x-neuronai-studio::ui.input type="number" wire:model="topK" min="1" max="100" placeholder="{{ config('neuronai-studio.rag.retrieval.top_k', 5) }}" />
                        @error('topK') <x-neuronai-studio::ui.form-error>{{ $message }}</x-neuronai-studio::ui.form-error> @enderror
                    </x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.form-group>
                        <x-neuronai-studio::ui.label>Similarity Threshold</x-neuronai-studio::ui.label>
                        <x-neuronai-studio::ui.input type="number" wire:model="threshold" step="0.01" min="0" max="1" placeholder="none" />
                        @error('threshold') <x-neuronai-studio::ui.form-error>{{ $message }}</x-neuronai-studio::ui.form-error> @enderror
                    </x-neuronai-studio::ui.form-group>
                </x-neuronai-studio::ui.form-row>

                @if ($vectorStoreFields !== [])
                    <div class="space-y-3 rounded-md border border-border p-3">
                        <p class="text-sm font-medium">Vector store settings</p>
                        <div class="grid gap-3 md:grid-cols-2">
                            @foreach ($vectorStoreFields as $field)
                                @php $fieldKey = $field['key'] ?? ''; @endphp
                                @if ($fieldKey !== '')
                                    <x-neuronai-studio::ui.form-group>
                                        <x-neuronai-studio::ui.label>
                                            {{ $field['label'] ?? $fieldKey }}
                                            @if (! empty($field['required']))
                                                <span class="text-destructive">*</span>
                                            @endif
                                        </x-neuronai-studio::ui.label>
                                        <x-neuronai-studio::ui.input
                                            type="{{ ($field['type'] ?? 'text') === 'number' ? 'number' : 'text' }}"
                                            wire:model="vectorStoreConfig.{{ $fieldKey }}"
                                            placeholder="{{ $field['placeholder'] ?? '' }}"
                                        />
                                    </x-neuronai-studio::ui.form-group>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="flex flex-wrap gap-2 pt-2">
                    <x-neuronai-studio::ui.button variant="outline" :href="route('neuronai-studio.knowledge-bases.index')">Cancel</x-neuronai-studio::ui.button>
                    <x-neuronai-studio::ui.button type="submit">Save Knowledge Base</x-neuronai-studio::ui.button>
                </div>
            </x-neuronai-studio::ui.card-content>
        </x-neuronai-studio::ui.card>
    </form>

    @if ($knowledgeBase?->exists)
        <x-neuronai-studio::ui.alert class="mt-4">
            Connect this knowledge base to an agent with a
            <a href="{{ $toolsCreateUrl }}" class="underline">RAG tool</a>
            (type <code class="text-xs">rag</code>), or add a <strong>RAG</strong> node upstream in a workflow.
        </x-neuronai-studio::ui.alert>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <x-neuronai-studio::ui.card>
                <x-neuronai-studio::ui.card-header>
                    <h3 class="font-semibold">Ingest Document</h3>
                </x-neuronai-studio::ui.card-header>
                <x-neuronai-studio::ui.card-content class="space-y-4">
                    <form wire:submit="ingestUpload" class="space-y-2">
                        <x-neuronai-studio::ui.label>Upload file (txt, md, pdf, html)</x-neuronai-studio::ui.label>
                        <input type="file" wire:model="upload" class="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-sm file:text-primary-foreground">
                        @error('upload') <x-neuronai-studio::ui.form-error>{{ $message }}</x-neuronai-studio::ui.form-error> @enderror
                        <div>
                            <x-neuronai-studio::ui.button type="submit" size="sm" wire:loading.attr="disabled" wire:target="upload,ingestUpload">
                                <span wire:loading.remove wire:target="ingestUpload">Ingest File</span>
                                <span wire:loading wire:target="ingestUpload">Ingesting...</span>
                            </x-neuronai-studio::ui.button>
                        </div>
                    </form>

                    <form wire:submit="ingestManualText" class="space-y-2 border-t border-border pt-4">
                        <x-neuronai-studio::ui.label>Or paste text</x-neuronai-studio::ui.label>
                        <x-neuronai-studio::ui.input type="text" wire:model="ingestTextName" placeholder="Document name (optional)" />
                        <x-neuronai-studio::ui.textarea wire:model="ingestText" rows="4" placeholder="Paste raw text to embed..."></x-neuronai-studio::ui.textarea>
                        @error('ingestText') <x-neuronai-studio::ui.form-error>{{ $message }}</x-neuronai-studio::ui.form-error> @enderror
                        <div>
                            <x-neuronai-studio::ui.button type="submit" size="sm" wire:loading.attr="disabled" wire:target="ingestManualText">
                                <span wire:loading.remove wire:target="ingestManualText">Ingest Text</span>
                                <span wire:loading wire:target="ingestManualText">Ingesting...</span>
                            </x-neuronai-studio::ui.button>
                        </div>
                    </form>
                </x-neuronai-studio::ui.card-content>
            </x-neuronai-studio::ui.card>

            <x-neuronai-studio::ui.card>
                <x-neuronai-studio::ui.card-header>
                    <h3 class="font-semibold">Retrieval Preview</h3>
                </x-neuronai-studio::ui.card-header>
                <x-neuronai-studio::ui.card-content class="space-y-3">
                    <form wire:submit="runSearch" class="space-y-2">
                        <x-neuronai-studio::ui.input type="text" wire:model="searchQuery" placeholder="Ask a question to preview chunks..." />
                        <x-neuronai-studio::ui.button type="submit" variant="outline" size="sm" wire:loading.attr="disabled" wire:target="runSearch">
                            <span wire:loading.remove wire:target="runSearch">Search</span>
                            <span wire:loading wire:target="runSearch">Searching...</span>
                        </x-neuronai-studio::ui.button>
                    </form>

                    @if ($searchError)
                        <x-neuronai-studio::ui.alert variant="error">{{ $searchError }}</x-neuronai-studio::ui.alert>
                    @endif

                    @if ($searchResults !== [])
                        <ul class="space-y-2">
                            @foreach ($searchResults as $result)
                                <li class="rounded-md border border-border p-2 text-xs">
                                    <div class="mb-1 flex items-center justify-between">
                                        <span class="font-mono text-muted-foreground">{{ $result['source_name'] ?? 'chunk' }}</span>
                                        <x-neuronai-studio::ui.badge variant="secondary">{{ number_format((float) ($result['score'] ?? 0), 3) }}</x-neuronai-studio::ui.badge>
                                    </div>
                                    <p class="text-muted-foreground">{{ \Illuminate\Support\Str::limit($result['content'] ?? '', 220) }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-neuronai-studio::ui.card-content>
            </x-neuronai-studio::ui.card>
        </div>

        <x-neuronai-studio::ui.card class="mt-4">
            <x-neuronai-studio::ui.card-header>
                <h3 class="font-semibold">Documents ({{ $documents->count() }})</h3>
            </x-neuronai-studio::ui.card-header>
            @if ($documents->isEmpty())
                <x-neuronai-studio::ui.card-content>
                    <p class="text-sm text-muted-foreground">No documents ingested yet.</p>
                </x-neuronai-studio::ui.card-content>
            @else
                <x-neuronai-studio::ui.table>
                    <x-neuronai-studio::ui.table-head>
                        <tr>
                            <x-neuronai-studio::ui.table-header>Name</x-neuronai-studio::ui.table-header>
                            <x-neuronai-studio::ui.table-header>Source</x-neuronai-studio::ui.table-header>
                            <x-neuronai-studio::ui.table-header>Chunks</x-neuronai-studio::ui.table-header>
                            <x-neuronai-studio::ui.table-header>Status</x-neuronai-studio::ui.table-header>
                            <x-neuronai-studio::ui.table-header></x-neuronai-studio::ui.table-header>
                        </tr>
                    </x-neuronai-studio::ui.table-head>
                    <x-neuronai-studio::ui.table-body>
                        @foreach ($documents as $document)
                            <x-neuronai-studio::ui.table-row wire:key="doc-{{ $document->id }}">
                                <x-neuronai-studio::ui.table-cell>
                                    <strong>{{ $document->name }}</strong>
                                    @if ($document->error)
                                        <div class="text-xs text-red-400">{{ \Illuminate\Support\Str::limit($document->error, 80) }}</div>
                                    @endif
                                </x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.table-cell>{{ $document->source_type }}</x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.table-cell>{{ $document->chunk_count }}</x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.table-cell>
                                    <x-neuronai-studio::ui.badge :variant="$statusVariants[$document->status] ?? 'draft'">{{ $document->status }}</x-neuronai-studio::ui.badge>
                                </x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.table-cell class="space-x-1 whitespace-nowrap">
                                    @if ($document->storage_key)
                                        <x-neuronai-studio::ui.button variant="ghost" size="sm" wire:click="reindexDocument({{ $document->id }})" wire:confirm="Re-embed this document?">Reindex</x-neuronai-studio::ui.button>
                                    @endif
                                    <x-neuronai-studio::ui.button variant="ghost" size="sm" wire:click="deleteDocument({{ $document->id }})" wire:confirm="Remove this document?" class="text-destructive hover:text-destructive">Delete</x-neuronai-studio::ui.button>
                                </x-neuronai-studio::ui.table-cell>
                            </x-neuronai-studio::ui.table-row>
                        @endforeach
                    </x-neuronai-studio::ui.table-body>
                </x-neuronai-studio::ui.table>
            @endif
        </x-neuronai-studio::ui.card>
    @else
        <x-neuronai-studio::ui.card class="mt-4">
            <x-neuronai-studio::ui.card-content class="pt-4">
                <p class="text-sm text-muted-foreground">Save the knowledge base to ingest documents and preview retrieval.</p>
            </x-neuronai-studio::ui.card-content>
        </x-neuronai-studio::ui.card>
    @endif
</x-neuronai-studio::ui.page>
