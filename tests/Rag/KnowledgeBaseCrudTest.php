<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Rag;

use DigitalElvis\NeuronAIStudio\Http\Livewire\KnowledgeBases\Edit;
use DigitalElvis\NeuronAIStudio\Http\Livewire\KnowledgeBases\Index;
use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Models\KnowledgeDocument;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\EmbeddingsFactory;
use DigitalElvis\NeuronAIStudio\Tests\Support\FakeEmbeddingsProvider;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use Livewire\Livewire;

class KnowledgeBaseCrudTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(EmbeddingsFactory::class)->extend('fake', fn () => new FakeEmbeddingsProvider);
    }

    protected function fakeKnowledgeBase(): KnowledgeBase
    {
        return KnowledgeBase::create([
            'name' => 'Support Docs',
            'embeddings_provider' => 'fake',
            'vector_store_driver' => 'memory',
        ]);
    }

    public function test_create_persists_knowledge_base_with_retrieval_defaults(): void
    {
        Livewire::test(Edit::class)
            ->set('name', 'Product Manual')
            ->set('embeddingsProvider', 'fake')
            ->set('embeddingsModel', 'fake-model')
            ->set('vectorStoreDriver', 'memory')
            ->set('topK', 3)
            ->set('threshold', 0.25)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $kb = KnowledgeBase::firstWhere('slug', 'product-manual');

        $this->assertNotNull($kb);
        $this->assertSame('fake', $kb->embeddings_provider);
        $this->assertSame('memory', $kb->vector_store_driver);
        $this->assertSame(3, $kb->retrieval_defaults['top_k']);
        $this->assertSame(0.25, $kb->retrieval_defaults['threshold']);
    }

    public function test_create_persists_vector_store_config_fields(): void
    {
        Livewire::test(Edit::class)
            ->set('name', 'Pinecone Docs')
            ->set('embeddingsProvider', 'fake')
            ->set('vectorStoreDriver', 'pinecone')
            ->set('vectorStoreConfig', [
                'key_env' => 'PINECONE_API_KEY',
                'index_url' => 'https://example.pinecone.io',
                'namespace' => 'studio',
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $kb = KnowledgeBase::firstWhere('slug', 'pinecone-docs');

        $this->assertNotNull($kb);
        $this->assertSame('pinecone', $kb->vector_store_driver);
        $this->assertSame('https://example.pinecone.io', $kb->vector_store_config['index_url']);
        $this->assertSame('studio', $kb->vector_store_config['namespace']);
    }

    public function test_create_requires_name(): void
    {
        Livewire::test(Edit::class)
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_ingest_text_creates_completed_document(): void
    {
        $kb = $this->fakeKnowledgeBase();

        Livewire::test(Edit::class, ['knowledgeBase' => $kb])
            ->set('ingestText', 'The refund window is thirty days from purchase.')
            ->set('ingestTextName', 'refunds')
            ->call('ingestManualText')
            ->assertHasNoErrors();

        $document = $kb->documents()->first();

        $this->assertNotNull($document);
        $this->assertSame('refunds', $document->name);
        $this->assertSame(KnowledgeDocument::STATUS_COMPLETED, $document->status);
        $this->assertGreaterThanOrEqual(1, $document->chunk_count);
    }

    public function test_ingest_text_requires_content(): void
    {
        $kb = $this->fakeKnowledgeBase();

        Livewire::test(Edit::class, ['knowledgeBase' => $kb])
            ->set('ingestText', '')
            ->call('ingestManualText')
            ->assertHasErrors(['ingestText' => 'required']);
    }

    public function test_delete_document_removes_row(): void
    {
        $kb = $this->fakeKnowledgeBase();
        $document = $kb->documents()->create([
            'name' => 'temp',
            'source_type' => 'manual',
            'status' => KnowledgeDocument::STATUS_COMPLETED,
        ]);

        Livewire::test(Edit::class, ['knowledgeBase' => $kb])
            ->call('deleteDocument', $document->id);

        $this->assertDatabaseMissing('knowledge_documents', ['id' => $document->id]);
    }

    public function test_run_search_previews_retrieved_chunks(): void
    {
        $kb = $this->fakeKnowledgeBase();

        Livewire::test(Edit::class, ['knowledgeBase' => $kb])
            ->set('ingestText', 'The premium plan costs ninety nine dollars per month.')
            ->call('ingestManualText')
            ->set('searchQuery', 'premium plan price')
            ->call('runSearch')
            ->assertSet('searchError', null)
            ->assertCount('searchResults', 1);
    }

    public function test_index_lists_and_deletes_knowledge_bases(): void
    {
        $kb = $this->fakeKnowledgeBase();

        Livewire::test(Index::class)
            ->assertSee('Support Docs')
            ->call('delete', $kb->id);

        $this->assertDatabaseMissing('knowledge_bases', ['id' => $kb->id]);
    }
}
