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
            $this->topK = isset($knowledgeBase->retrieval_defaults['top_k'])
                ? (int) $knowledgeBase->retrieval_defaults['top_k']
                : null;
            $this->threshold = isset($knowledgeBase->retrieval_defaults['threshold'])
                ? (float) $knowledgeBase->retrieval_defaults['threshold']
                : null;
        } else {
            $this->embeddingsModel = $this->defaultModelForProvider($this->embeddingsProvider);
        }
    }

    public function updatedEmbeddingsProvider(string $value): void
    {
        $this->embeddingsModel = $this->defaultModelForProvider($value);
    }

    protected function defaultModelForProvider(string $provider): string
    {
        return (string) config(
            "neuronai-studio.rag.embeddings.{$provider}.default_model",
            config('neuronai-studio.rag.default_embeddings_model', 'text-embedding-3-small')
        );
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'embeddingsProvider' => 'required|string|max:255',
            'embeddingsModel' => 'nullable|string|max:255',
            'vectorStoreDriver' => 'required|string|max:255',
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

        $ingest->ingestFile(
            $this->knowledgeBase,
            $this->upload->getRealPath(),
            $this->upload->getClientOriginalName(),
        );

        $this->reset('upload');
        session()->flash('success', 'Document ingested.');
    }

    public function ingestManualText(DocumentIngestService $ingest): void
    {
        $this->requirePersisted();

        $this->validate([
            'ingestText' => 'required|string',
            'ingestTextName' => 'nullable|string|max:255',
        ]);

        $ingest->ingestText(
            $this->knowledgeBase,
            $this->ingestText,
            $this->ingestTextName !== '' ? $this->ingestTextName : 'text',
        );

        $this->reset('ingestText', 'ingestTextName');
        session()->flash('success', 'Text ingested.');
    }

    public function deleteDocument(int $documentId): void
    {
        $this->requirePersisted();

        $this->knowledgeBase->documents()
            ->whereKey($documentId)
            ->first()?->delete();

        session()->flash('success', 'Document removed.');
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

        return view('neuronai-studio::livewire.knowledge-bases.edit', [
            'documents' => $documents,
            'providers' => (array) config('neuronai-studio.rag.embeddings', []),
            'models' => (array) config("neuronai-studio.rag.embeddings.{$this->embeddingsProvider}.models", []),
            'vectorStores' => (array) config('neuronai-studio.rag.vector_stores', []),
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
