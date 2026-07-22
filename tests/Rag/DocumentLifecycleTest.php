<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Rag;

use DigitalElvis\NeuronAIStudio\Jobs\IngestKnowledgeDocumentJob;
use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Models\KnowledgeDocument;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\DocumentIngestService;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\EmbeddingsFactory;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\RagRetrievalService;
use DigitalElvis\NeuronAIStudio\Tests\Support\FakeEmbeddingsProvider;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

class DocumentLifecycleTest extends TestCase
{
    protected string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storagePath = sys_get_temp_dir().'/neuronai-studio-rag-life-'.uniqid('', true);
        mkdir($this->storagePath, 0o755, true);
        config()->set('neuronai-studio.rag.storage_path', $this->storagePath);

        app(EmbeddingsFactory::class)->extend('fake', fn () => new FakeEmbeddingsProvider);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->storagePath)) {
            foreach (glob($this->storagePath.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->storagePath);
        }

        parent::tearDown();
    }

    protected function knowledgeBase(string $driver = 'file'): KnowledgeBase
    {
        return KnowledgeBase::create([
            'name' => 'Lifecycle KB',
            'embeddings_provider' => 'fake',
            'vector_store_driver' => $driver,
        ]);
    }

    public function test_ingest_persists_storage_key_and_stable_vector_source(): void
    {
        $kb = $this->knowledgeBase('memory');
        $document = app(DocumentIngestService::class)->ingestText($kb, 'Stable source naming content.', 'policy');

        $this->assertSame(KnowledgeDocument::STATUS_COMPLETED, $document->status);
        $this->assertNotNull($document->storage_key);
        $this->assertSame('doc:'.$document->id, $document->vectorSourceName());
    }

    public function test_delete_document_removes_vectors_from_file_store(): void
    {
        $kb = $this->knowledgeBase('file');
        $ingest = app(DocumentIngestService::class);
        $document = $ingest->ingestText($kb, 'The refund window is thirty days.', 'refunds');

        $this->assertNotEmpty(app(RagRetrievalService::class)->search($kb, 'refund window'));

        $ingest->removeDocument($document);

        $this->assertDatabaseMissing('knowledge_documents', ['id' => $document->id]);
        $this->assertSame([], app(RagRetrievalService::class)->search($kb, 'refund window'));
    }

    public function test_reindex_rebuilds_embeddings_from_storage(): void
    {
        $kb = $this->knowledgeBase('memory');
        $ingest = app(DocumentIngestService::class);
        $document = $ingest->ingestText($kb, 'Original pricing is ninety nine dollars.', 'pricing');

        $this->assertSame(KnowledgeDocument::STATUS_COMPLETED, $document->status);

        $reingested = $ingest->reindex($document->fresh());

        $this->assertSame(KnowledgeDocument::STATUS_COMPLETED, $reingested->status);
        $this->assertGreaterThanOrEqual(1, $reingested->chunk_count);
        $this->assertNotEmpty(app(RagRetrievalService::class)->search($kb, 'pricing dollars'));
    }

    public function test_remove_knowledge_base_deletes_file_store(): void
    {
        $kb = $this->knowledgeBase('file');
        $ingest = app(DocumentIngestService::class);
        $ingest->ingestText($kb, 'Cleanup file store content.', 'cleanup');

        $storeFile = $this->storagePath.'/'.$kb->slug.'.store';
        $this->assertFileExists($storeFile);

        $ingest->removeKnowledgeBase($kb);

        $this->assertDatabaseMissing('knowledge_bases', ['id' => $kb->id]);
        $this->assertFileDoesNotExist($storeFile);
    }

    public function test_queue_text_dispatches_ingest_job(): void
    {
        Queue::fake();
        config()->set('neuronai-studio.rag.async_ingest', true);

        $kb = $this->knowledgeBase('memory');
        $document = app(DocumentIngestService::class)->queueText($kb, 'Queued content for ingest.', 'queued');

        $this->assertSame(KnowledgeDocument::STATUS_PENDING, $document->status);
        $this->assertNotNull($document->storage_key);

        Queue::assertPushed(IngestKnowledgeDocumentJob::class, function (IngestKnowledgeDocumentJob $job) use ($document) {
            return $job->documentId === $document->id;
        });
    }

    public function test_ingest_job_processes_pending_document(): void
    {
        $kb = $this->knowledgeBase('memory');
        $ingest = app(DocumentIngestService::class);

        Queue::fake();
        $document = $ingest->queueText($kb, 'Job processes this text body.', 'job-doc');

        (new IngestKnowledgeDocumentJob($document->id))->handle($ingest);

        $document->refresh();
        $this->assertSame(KnowledgeDocument::STATUS_COMPLETED, $document->status);
        $this->assertGreaterThanOrEqual(1, $document->chunk_count);
    }
}
