<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Tools;

use DigitalElvis\NeuronAIStudio\Http\Livewire\Tools\Edit;
use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Models\ToolDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\EmbeddingsFactory;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Tests\Support\FakeEmbeddingsProvider;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use DigitalElvis\NeuronAIStudio\Tools\KnowledgeBaseTool;
use Livewire\Livewire;

class RagToolEditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(EmbeddingsFactory::class)->extend('fake', fn () => new FakeEmbeddingsProvider);
    }

    public function test_save_rag_tool_persists_definition(): void
    {
        $kb = KnowledgeBase::create([
            'name' => 'Product Docs',
            'embeddings_provider' => 'fake',
            'vector_store_driver' => 'memory',
        ]);

        Livewire::test(Edit::class)
            ->set('toolKind', 'rag')
            ->set('name', 'Search Product Docs')
            ->set('toolName', 'search_product_docs')
            ->set('description', 'Search product documentation.')
            ->set('knowledgeBaseId', $kb->getKey())
            ->set('topK', 3)
            ->set('threshold', 0.5)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $tool = ToolDefinition::firstWhere('slug', 'search-product-docs');

        $this->assertNotNull($tool);
        $this->assertSame('rag', $tool->type);
        $this->assertSame($kb->getKey(), $tool->config['knowledge_base_id']);
        $this->assertSame('search_product_docs', $tool->config['tool_name']);
        $this->assertSame(3, $tool->config['top_k']);
        $this->assertSame(0.5, $tool->config['threshold']);
        $this->assertSame('query', $tool->input_schema[0]['name']);
    }

    public function test_tool_resolver_resolves_rag_database_tool(): void
    {
        $kb = KnowledgeBase::create([
            'name' => 'FAQ',
            'embeddings_provider' => 'fake',
            'vector_store_driver' => 'memory',
        ]);

        $tool = ToolDefinition::create([
            'name' => 'Search FAQ',
            'slug' => 'search-faq',
            'type' => 'rag',
            'description' => 'Search FAQ knowledge base.',
            'input_schema' => [
                ['name' => 'query', 'type' => 'string', 'description' => 'Query', 'required' => true],
            ],
            'config' => [
                'tool_name' => 'search_faq',
                'knowledge_base_id' => $kb->getKey(),
            ],
        ]);

        $resolved = app(ToolResolver::class)->resolve($tool->bindingRef());

        $this->assertCount(1, $resolved);
        $this->assertInstanceOf(KnowledgeBaseTool::class, $resolved[0]);
        $this->assertSame('search_faq', $resolved[0]->getName());
    }
}
