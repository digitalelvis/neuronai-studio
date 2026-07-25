<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Rag;

use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\VectorStoreFactory;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use InvalidArgumentException;
use NeuronAI\RAG\VectorStore\MemoryVectorStore;
use NeuronAI\RAG\VectorStore\PineconeVectorStore;

class VectorStoreFactoryTest extends TestCase
{
    public function test_registers_neuron_first_party_drivers(): void
    {
        $factory = app(VectorStoreFactory::class);

        foreach ([
            'file', 'memory', 'pinecone', 'qdrant', 'chroma', 'weaviate',
            'meilisearch', 'mariadb', 'elasticsearch', 'opensearch',
            'typesense', 'phpvector',
        ] as $driver) {
            $this->assertTrue($factory->has($driver), "Missing driver [{$driver}]");
        }
    }

    public function test_memory_store_is_cached_per_knowledge_base(): void
    {
        $kb = KnowledgeBase::create([
            'name' => 'Memory KB',
            'vector_store_driver' => 'memory',
        ]);

        $factory = app(VectorStoreFactory::class);
        $first = $factory->make($kb);
        $second = $factory->make($kb);

        $this->assertInstanceOf(MemoryVectorStore::class, $first);
        $this->assertSame($first, $second);
    }

    public function test_pinecone_builds_from_vector_store_config(): void
    {
        $kb = KnowledgeBase::create([
            'name' => 'Pinecone KB',
            'vector_store_driver' => 'pinecone',
            'vector_store_config' => [
                'key_env' => 'PINECONE_API_KEY',
                'index_url' => 'https://example.pinecone.io',
                'namespace' => 'studio',
            ],
        ]);

        putenv('PINECONE_API_KEY=test-key');
        $_ENV['PINECONE_API_KEY'] = 'test-key';

        try {
            $store = app(VectorStoreFactory::class)->make($kb);
            $this->assertInstanceOf(PineconeVectorStore::class, $store);
        } finally {
            putenv('PINECONE_API_KEY');
            unset($_ENV['PINECONE_API_KEY']);
        }
    }

    public function test_pinecone_requires_index_url(): void
    {
        $kb = KnowledgeBase::create([
            'name' => 'Pinecone Missing',
            'vector_store_driver' => 'pinecone',
            'vector_store_config' => [],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('index_url');

        app(VectorStoreFactory::class)->make($kb);
    }

    public function test_optional_package_drivers_fail_with_composer_hint(): void
    {
        if (class_exists(\Elastic\Elasticsearch\ClientBuilder::class)) {
            $this->markTestSkipped('elasticsearch/elasticsearch is installed.');
        }

        $kb = KnowledgeBase::create([
            'name' => 'ES KB',
            'vector_store_driver' => 'elasticsearch',
            'vector_store_config' => ['index' => 'neuron-ai'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('composer require elasticsearch/elasticsearch');

        app(VectorStoreFactory::class)->make($kb);
    }

    public function test_unknown_driver_throws(): void
    {
        $kb = KnowledgeBase::create([
            'name' => 'Unknown',
            'vector_store_driver' => 'not-a-driver',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not-a-driver');

        app(VectorStoreFactory::class)->make($kb);
    }

    public function test_file_store_path_helper(): void
    {
        $path = sys_get_temp_dir().'/neuronai-studio-vsf-'.uniqid('', true);
        config()->set('neuronai-studio.rag.storage_path', $path);

        $kb = KnowledgeBase::create([
            'name' => 'File Path',
            'slug' => 'file-path',
            'vector_store_driver' => 'file',
        ]);

        $this->assertSame(
            $path.DIRECTORY_SEPARATOR.'file-path.store',
            app(VectorStoreFactory::class)->fileStorePath($kb),
        );
    }
}
