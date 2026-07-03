<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\RagNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\DocumentIngestService;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\EmbeddingsFactory;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;
use DigitalElvis\NeuronAIStudio\Tests\Support\FakeEmbeddingsProvider;
use RuntimeException;

class RagNodeExecutorTest extends TestCase
{
    protected function knowledgeBaseWithDocs(): KnowledgeBase
    {
        app(EmbeddingsFactory::class)->extend('fake', fn () => new FakeEmbeddingsProvider);

        $kb = KnowledgeBase::create([
            'name' => 'Product KB',
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

    protected function stateWith(array $data = []): BuilderWorkflowState
    {
        return new BuilderWorkflowState(new GraphContext([], []), null, $data);
    }

    public function test_executor_populates_rag_context_from_retrieval(): void
    {
        $kb = $this->knowledgeBaseWithDocs();
        $state = $this->stateWith(['input' => 'How much is the premium plan?']);

        $result = app(RagNodeExecutor::class)->execute([
            'id' => 'rag_1',
            'data' => ['knowledge_base_id' => $kb->getKey()],
        ], $state, $state->graphContext);

        $this->assertSame('default', $result);

        $ragContext = $state->get('rag_context');
        $this->assertIsArray($ragContext);
        $this->assertNotEmpty($ragContext['results']);
        $this->assertSame($kb->getKey(), $ragContext['knowledge_base_id']);
        $this->assertStringContainsString('premium plan', $ragContext['context']);
        $this->assertGreaterThan(0, $ragContext['top_score']);
    }

    public function test_downstream_agent_can_interpolate_rag_context(): void
    {
        $kb = $this->knowledgeBaseWithDocs();
        $state = $this->stateWith(['input' => 'premium plan price']);

        app(RagNodeExecutor::class)->execute([
            'id' => 'rag_1',
            'data' => ['knowledge_base_id' => $kb->getKey()],
        ], $state, $state->graphContext);

        $prompt = StateTemplateInterpolator::interpolate(
            'Answer using context: {{ rag_context.context }}',
            $state,
        );

        $this->assertStringContainsString('premium plan', $prompt);
    }

    public function test_executor_uses_custom_output_key(): void
    {
        $kb = $this->knowledgeBaseWithDocs();
        $state = $this->stateWith(['input' => 'pricing']);

        app(RagNodeExecutor::class)->execute([
            'id' => 'rag_1',
            'data' => [
                'knowledge_base_id' => $kb->getKey(),
                'output_key' => 'kb_hits',
            ],
        ], $state, $state->graphContext);

        $this->assertIsArray($state->get('kb_hits'));
        $this->assertNull($state->get('rag_context'));
    }

    public function test_executor_requires_knowledge_base_id(): void
    {
        $state = $this->stateWith(['input' => 'anything']);

        $this->expectException(RuntimeException::class);

        app(RagNodeExecutor::class)->execute([
            'id' => 'rag_1',
            'data' => [],
        ], $state, $state->graphContext);
    }
}
