<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Rag;

use DigitalElvis\NeuronAIStudio\Http\Middleware\EnsureNeuronAIStudioAuthorized;
use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\DocumentIngestService;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\EmbeddingsFactory;
use DigitalElvis\NeuronAIStudio\Tests\Support\FakeEmbeddingsProvider;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class KnowledgeBaseSearchControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);
        app(EmbeddingsFactory::class)->extend('fake', fn () => new FakeEmbeddingsProvider);
    }

    protected function knowledgeBaseWithDocs(): KnowledgeBase
    {
        $kb = KnowledgeBase::create([
            'name' => 'Pricing KB',
            'embeddings_provider' => 'fake',
            'vector_store_driver' => 'memory',
        ]);

        app(DocumentIngestService::class)->ingestText(
            $kb,
            'The premium plan costs ninety nine dollars per month and includes priority support.',
            'pricing',
        );

        return $kb;
    }

    public function test_search_returns_matching_chunks(): void
    {
        $kb = $this->knowledgeBaseWithDocs();

        $response = $this->postJson(route('neuronai-studio.knowledge-bases.search', $kb), [
            'query' => 'How much is the premium plan?',
        ]);

        $response->assertOk();
        $response->assertJsonPath('knowledge_base_id', $kb->getKey());
        $this->assertGreaterThanOrEqual(1, $response->json('chunk_count'));
        $this->assertStringContainsString('premium plan', $response->json('results.0.content'));
    }

    public function test_search_requires_query(): void
    {
        $kb = $this->knowledgeBaseWithDocs();

        $response = $this->postJson(route('neuronai-studio.knowledge-bases.search', $kb), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['query']);
    }
}
