<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Rag;

use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\DocumentIngestService;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\EmbeddingsFactory;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\RagRetrievalService;
use DigitalElvis\NeuronAIStudio\Tests\Support\FakeEmbeddingsProvider;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class RagRetrievalServiceTest extends TestCase
{
    protected function seededKnowledgeBase(): KnowledgeBase
    {
        app(EmbeddingsFactory::class)->extend('fake', fn () => new FakeEmbeddingsProvider);

        $kb = KnowledgeBase::create([
            'name' => 'Support KB',
            'embeddings_provider' => 'fake',
            'vector_store_driver' => 'memory',
        ]);

        $ingest = app(DocumentIngestService::class);
        $ingest->ingestText($kb, 'Refunds are processed within five business days of approval.', 'refunds');
        $ingest->ingestText($kb, 'Our office is open from nine in the morning to six in the evening.', 'hours');

        return $kb;
    }

    public function test_search_returns_most_relevant_chunk_first(): void
    {
        $kb = $this->seededKnowledgeBase();

        $results = app(RagRetrievalService::class)->search($kb, 'How long do refunds take?', ['top_k' => 1]);

        $this->assertNotEmpty($results);
        $this->assertStringContainsString('Refunds', $results[0]['content']);
        $this->assertArrayHasKey('score', $results[0]);
        $this->assertArrayHasKey('source_name', $results[0]);
        $this->assertSame('refunds', $results[0]['source_name']);
    }

    public function test_search_respects_top_k(): void
    {
        $kb = $this->seededKnowledgeBase();

        $results = app(RagRetrievalService::class)->search($kb, 'refunds office hours', ['top_k' => 2]);

        $this->assertLessThanOrEqual(2, count($results));
    }

    public function test_search_returns_empty_for_blank_query(): void
    {
        $kb = $this->seededKnowledgeBase();

        $this->assertSame([], app(RagRetrievalService::class)->search($kb, '   '));
    }

    public function test_threshold_filters_low_scoring_results(): void
    {
        $kb = $this->seededKnowledgeBase();

        $results = app(RagRetrievalService::class)->search($kb, 'refunds', [
            'top_k' => 5,
            'threshold' => 0.99,
        ]);

        $this->assertIsArray($results);

        foreach ($results as $result) {
            $this->assertGreaterThanOrEqual(0.99, $result['score']);
        }
    }
}
