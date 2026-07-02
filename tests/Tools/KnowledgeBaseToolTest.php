<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Tools;

use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Models\ToolDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\DocumentIngestService;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\EmbeddingsFactory;
use DigitalElvis\NeuronAIStudio\Tools\KnowledgeBaseTool;
use DigitalElvis\NeuronAIStudio\Tests\Support\FakeEmbeddingsProvider;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use NeuronAI\Tools\Tool;

class KnowledgeBaseToolTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(EmbeddingsFactory::class)->extend('fake', fn () => new FakeEmbeddingsProvider);
    }

    protected function knowledgeBaseWithDocs(): KnowledgeBase
    {
        $kb = KnowledgeBase::create([
            'name' => 'Support KB',
            'embeddings_provider' => 'fake',
            'vector_store_driver' => 'memory',
        ]);

        app(DocumentIngestService::class)->ingestText(
            $kb,
            'Refunds are processed within five business days after approval.',
            'refunds',
        );

        return $kb;
    }

    public function test_from_definition_builds_tool_with_query_property(): void
    {
        $kb = $this->knowledgeBaseWithDocs();

        $definition = ToolDefinition::create([
            'name' => 'Search Support KB',
            'slug' => 'search-support-kb',
            'type' => 'rag',
            'description' => 'Search support documentation.',
            'input_schema' => [
                ['name' => 'query', 'type' => 'string', 'description' => 'Search query', 'required' => true],
            ],
            'config' => [
                'tool_name' => 'search_support_kb',
                'knowledge_base_id' => $kb->getKey(),
            ],
        ]);

        $tool = KnowledgeBaseTool::fromDefinition($definition);

        $this->assertInstanceOf(Tool::class, $tool);
        $this->assertSame('search_support_kb', $tool->getName());
    }

    public function test_execute_returns_matching_context(): void
    {
        $kb = $this->knowledgeBaseWithDocs();

        $tool = KnowledgeBaseTool::fromDefinition(ToolDefinition::create([
            'name' => 'Search Support KB',
            'slug' => 'search-support-kb',
            'type' => 'rag',
            'description' => 'Search support documentation.',
            'input_schema' => [],
            'config' => [
                'tool_name' => 'search_support_kb',
                'knowledge_base_id' => $kb->getKey(),
            ],
        ]));

        $tool->setInputs(['query' => 'How long do refunds take?']);
        $tool->execute();

        $result = $tool->getResult();

        $this->assertIsString($result);
        $this->assertStringContainsString('Refunds', $result);
        $this->assertStringContainsString('[refunds]', $result);
    }

    public function test_execute_returns_message_when_no_results(): void
    {
        $kb = KnowledgeBase::create([
            'name' => 'Empty KB',
            'embeddings_provider' => 'fake',
            'vector_store_driver' => 'memory',
        ]);

        $tool = KnowledgeBaseTool::fromDefinition(ToolDefinition::create([
            'name' => 'Search Empty KB',
            'slug' => 'search-empty-kb',
            'type' => 'rag',
            'description' => 'Search empty knowledge base.',
            'input_schema' => [],
            'config' => [
                'tool_name' => 'search_empty_kb',
                'knowledge_base_id' => $kb->getKey(),
            ],
        ]));

        $tool->setInputs(['query' => 'anything']);
        $tool->execute();

        $this->assertStringContainsString('No matching documents', $tool->getResult());
    }
}
