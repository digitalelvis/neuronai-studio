<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Rag;

use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Models\KnowledgeDocument;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\DocumentIngestService;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\EmbeddingsFactory;
use DigitalElvis\NeuronAIStudio\Tests\Support\FakeEmbeddingsProvider;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class DocumentIngestServiceTest extends TestCase
{
    protected function knowledgeBase(string $provider = 'fake'): KnowledgeBase
    {
        app(EmbeddingsFactory::class)->extend('fake', fn () => new FakeEmbeddingsProvider);

        return KnowledgeBase::create([
            'name' => 'Docs KB',
            'embeddings_provider' => $provider,
            'vector_store_driver' => 'memory',
        ]);
    }

    public function test_ingest_text_chunks_embeds_and_marks_completed(): void
    {
        $kb = $this->knowledgeBase();

        $document = app(DocumentIngestService::class)->ingestText(
            $kb,
            'Neuron Studio ships a visual workflow builder. It supports RAG nodes for retrieval.',
            'intro',
        );

        $this->assertSame(KnowledgeDocument::STATUS_COMPLETED, $document->status);
        $this->assertGreaterThanOrEqual(1, $document->chunk_count);
        $this->assertSame('intro', $document->name);
        $this->assertSame('manual', $document->source_type);
        $this->assertNull($document->error);
    }

    public function test_ingest_failure_marks_document_failed_with_error(): void
    {
        $kb = KnowledgeBase::create([
            'name' => 'Broken KB',
            'embeddings_provider' => 'does-not-exist',
            'vector_store_driver' => 'memory',
        ]);

        $document = app(DocumentIngestService::class)->ingestText($kb, 'some content', 'bad');

        $this->assertSame(KnowledgeDocument::STATUS_FAILED, $document->status);
        $this->assertNotNull($document->error);
        $this->assertStringContainsString('does-not-exist', $document->error);
    }
}
