<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\KnowledgeBases;

use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Models\KnowledgeDocument;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\DocumentIngestService;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\RagRetrievalService;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class Edit extends Component
{
    use WithFileUploads;

    public ?KnowledgeBase $knowledgeBase = null;

    public string $name = '';

    public string $description = '';

    public string $embeddingsProvider = '';

    public string $embeddingsModel = '';

    public string $vectorStoreDriver = '';

    /** @var array<string, mixed> */
    public array $vectorStoreConfig = [];

    public ?int $topK = null;

    public ?float $threshold = null;

    /** Uploaded file pending ingest. */
    public $upload = null;

    public string $ingestText = '';

    public string $ingestTextName = '';

    /** Debug search (retrieval preview) on the edit page. */
    public string $searchQuery = '';

    /** @var array<int, array<string, mixed>> */
    public array $searchResults = [];

    public ?string $searchError = null;

    public function mount(?KnowledgeBase $knowledgeBase = null): void
    {
        $this->knowledgeBase = $knowledgeBase;
        $this->embeddingsProvider = (string) config('neuronai-studio.rag.default_embeddings_provider', 'openai');
        $this->vectorStoreDriver = (string) config('neuronai-studio.rag.default_vector_store', 'file');

        if ($knowledgeBase?->exists) {
            $this->name = $knowledgeBase->name;
            $this->description = (string) $knowledgeBase->description;
            $this->embeddingsProvider = $knowledgeBase->embeddingsProvider();
            $this->embeddingsModel = (string) $knowledgeBase->embeddings_model;
            $this->vectorStoreDriver = $knowledgeBase->vectorStoreDriver();
            $this->vectorStoreConfig = (array) ($knowledgeBase->vector_store_config ?? []);
            $this->topK = isset($knowledgeBase->retrieval_defaults['top_k'])
                ? (int) $knowledgeBase->retrieval_defaults['top_k']
                : null;
            $this->threshold = isset($knowledgeBase->retrieval_defaults['threshold'])
                ? (float) $knowledgeBase->retrieval_defaults['threshold']
                : null;
        } else {
            $this->embeddingsModel = $this->defaultModelForProvider($this->embeddingsProvider);
        }

        $this->applyDriverFieldDefaults($this->vectorStoreDriver);
    }

    public function updatedEmbeddingsProvider(string $value): void
    {
        $this->embeddingsModel = $this->defaultModelForProvider($value);
    }

    public function updatedVectorStoreDriver(string $value): void
    {
        $this->vectorStoreConfig = [];
        $this->applyDriverFieldDefaults($value);
    }

    protected function defaultModelForProvider(string $provider): string
    {
        return (string) config(
            "neuronai-studio.rag.embeddings.{$provider}.default_model",
            config('neuronai-studio.rag.default_embeddings_model', 'text-embedding-3-small')
        );
    }

    protected function applyDriverFieldDefaults(string $driver): void
    {
        $fields = (array) config("neuronai-studio.rag.vector_stores.{$driver}.fields", []);

        foreach ($fields as $field) {
            $key = (string) ($field['key'] ?? '');

            if ($key === '' || array_key_exists($key, $this->vectorStoreConfig)) {
                continue;
            }

            if (array_key_exists('default', $field)) {
                $this->vectorStoreConfig[$key] = $field['default'];
            }
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'embeddingsProvider' => 'required|string|max:255',
            'embeddingsModel' => 'nullable|string|max:255',
            'vectorStoreDriver' => 'required|string|max:255',
            'vectorStoreConfig' => 'nullable|array',
            'topK' => 'nullable|integer|min:1|max:100',
            'threshold' => 'nullable|numeric|min:0|max:1',
        ]);

        $payload = [
            'name' => $validated['name'],
            'slug' => $this->knowledgeBase?->slug ?: Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'embeddings_provider' => $validated['embeddingsProvider'],
            'embeddings_model' => $validated['embeddingsModel'] ?: null,
            'vector_store_driver' => $validated['vectorStoreDriver'],
            'vector_store_config' => $this->normalizedVectorStoreConfig(),
            'retrieval_defaults' => $this->retrievalDefaults(),
            'source' => 'studio',
        ];

        if ($this->knowledgeBase?->exists) {
            $this->knowledgeBase->update($payload);
            session()->flash('success', 'Knowledge base updated.');

            return;
        }

        $this->knowledgeBase = KnowledgeBase::create($payload);
        session()->flash('success', 'Knowledge base created.');

        $this->redirect(route('neuronai-studio.knowledge-bases.edit', $this->knowledgeBase));
    }

    /** @return array<string, mixed> */
    protected function normalizedVectorStoreConfig(): array
    {
        $config = [];

        foreach ($this->vectorStoreConfig as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $config[(string) $key] = is_numeric($value) && ! is_string($value)
                ? $value
                : (is_string($value) && is_numeric($value) && ! str_contains($value, '.')
                    ? (str_contains((string) $key, 'dimension') || $key === 'port' || $key === 'vector_dimension'
                        ? (int) $value
                        : $value)
                    : $value);
        }

        return $config;
    }

    /** @return array<string, mixed> */
    protected function retrievalDefaults(): array
    {
        $defaults = [];

        if ($this->topK !== null) {
            $defaults['top_k'] = (int) $this->topK;
        }

        if ($this->threshold !== null) {
            $defaults['threshold'] = (float) $this->threshold;
        }

        return $defaults;
    }

    public function ingestUpload(DocumentIngestService $ingest): void
    {
        $this->requirePersisted();

        $this->validate([
            'upload' => 'required|file|max:20480',
        ]);

        if ($this->asyncIngest()) {
            $ingest->queueUpload($this->knowledgeBase, $this->upload);
            session()->flash('success', 'Document queued for ingest.');
        } else {
            $ingest->ingestFile(
                $this->knowledgeBase,
                $this->upload->getRealPath(),
                $this->upload->getClientOriginalName(),
            );
            session()->flash('success', 'Document ingested.');
        }

        $this->reset('upload');
    }

    public function ingestManualText(DocumentIngestService $ingest): void
    {
        $this->requirePersisted();

        $this->validate([
            'ingestText' => 'required|string',
            'ingestTextName' => 'nullable|string|max:255',
        ]);

        $name = $this->ingestTextName !== '' ? $this->ingestTextName : 'text';

        if ($this->asyncIngest()) {
            $ingest->queueText($this->knowledgeBase, $this->ingestText, $name);
            session()->flash('success', 'Text queued for ingest.');
        } else {
            $ingest->ingestText($this->knowledgeBase, $this->ingestText, $name);
            session()->flash('success', 'Text ingested.');
        }

        $this->reset('ingestText', 'ingestTextName');
    }

    public function deleteDocument(int $documentId, DocumentIngestService $ingest): void
    {
        $this->requirePersisted();

        $document = $this->knowledgeBase->documents()->whereKey($documentId)->first();

        if ($document !== null) {
            $ingest->removeDocument($document);
        }

        session()->flash('success', 'Document removed.');
    }

    public function reindexDocument(int $documentId, DocumentIngestService $ingest): void
    {
        $this->requirePersisted();

        $document = $this->knowledgeBase->documents()->whereKey($documentId)->first();

        if ($document === null) {
            return;
        }

        if ($this->asyncIngest()) {
            $ingest->queueReindex($document);
            session()->flash('success', 'Document queued for reindex.');
        } else {
            $ingest->reindex($document);
            session()->flash('success', 'Document reindexed.');
        }
    }

    public function runSearch(RagRetrievalService $retrieval): void
    {
        $this->requirePersisted();
        $this->searchResults = [];
        $this->searchError = null;

        if (trim($this->searchQuery) === '') {
            $this->searchError = 'Enter a query to preview retrieval.';

            return;
        }

        try {
            $this->searchResults = $retrieval->search($this->knowledgeBase, $this->searchQuery, [
                'top_k' => $this->topK,
                'threshold' => $this->threshold,
            ]);

            if ($this->searchResults === []) {
                $this->searchError = 'No matching chunks. Ingest documents or adjust the threshold.';
            }
        } catch (\Throwable $exception) {
            $this->searchError = $exception->getMessage();
        }
    }

    protected function asyncIngest(): bool
    {
        return (bool) config('neuronai-studio.rag.async_ingest', true);
    }

    protected function requirePersisted(): void
    {
        if (! $this->knowledgeBase?->exists) {
            $this->save();
        }
    }

    public function render()
    {
        $documents = $this->knowledgeBase?->exists
            ? $this->knowledgeBase->documents()->latest()->get()
            : collect();

        $driverMeta = (array) config("neuronai-studio.rag.vector_stores.{$this->vectorStoreDriver}", []);

        return view('neuronai-studio::livewire.knowledge-bases.edit', [
            'documents' => $documents,
            'providers' => (array) config('neuronai-studio.rag.embeddings', []),
            'models' => (array) config("neuronai-studio.rag.embeddings.{$this->embeddingsProvider}.models", []),
            'vectorStores' => (array) config('neuronai-studio.rag.vector_stores', []),
            'vectorStoreFields' => (array) ($driverMeta['fields'] ?? []),
            'vectorStoreDescription' => (string) ($driverMeta['description'] ?? ''),
            'toolsCreateUrl' => route('neuronai-studio.tools.create'),
            'statuses' => [
                KnowledgeDocument::STATUS_PENDING,
                KnowledgeDocument::STATUS_PROCESSING,
                KnowledgeDocument::STATUS_COMPLETED,
                KnowledgeDocument::STATUS_FAILED,
            ],
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [
                ['label' => 'Knowledge Bases', 'url' => route('neuronai-studio.knowledge-bases.index')],
                ['label' => $this->knowledgeBase?->exists ? $this->name : 'New Knowledge Base'],
            ],
            title: $this->knowledgeBase?->exists ? 'Edit Knowledge Base' : 'Create Knowledge Base',
        ));
    }
}
