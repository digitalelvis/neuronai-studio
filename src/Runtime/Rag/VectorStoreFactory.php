<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Rag;

use Closure;
use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use InvalidArgumentException;
use NeuronAI\RAG\VectorStore\MemoryVectorStore;
use NeuronAI\RAG\VectorStore\VectorStoreInterface;

/**
 * Resolves a vector store for a knowledge base. The packaged defaults are the
 * zero-infra `file` store and the volatile `memory` store; additional drivers
 * (pinecone, qdrant, chroma, pgvector, ...) can be registered by the host app
 * via {@see VectorStoreFactory::extend()}.
 */
class VectorStoreFactory
{
    /** @var array<string, Closure(KnowledgeBase, array<string, mixed>): VectorStoreInterface> */
    protected array $resolvers = [];

    /** In-memory stores are cached per knowledge base so ingest + retrieval share state. */
    protected array $memoryStores = [];

    public function __construct()
    {
        $this->registerDefaults();
    }

    /**
     * Register (or override) a resolver for a vector store driver.
     *
     * @param  Closure(KnowledgeBase, array<string, mixed>): VectorStoreInterface  $resolver
     */
    public function extend(string $driver, Closure $resolver): void
    {
        $this->resolvers[$driver] = $resolver;
    }

    public function has(string $driver): bool
    {
        return isset($this->resolvers[$driver]);
    }

    /**
     * @param  array<string, mixed>  $options  e.g. ['top_k' => 5]
     */
    public function make(KnowledgeBase $knowledgeBase, array $options = []): VectorStoreInterface
    {
        $driver = $knowledgeBase->vectorStoreDriver();

        if (! isset($this->resolvers[$driver])) {
            throw new InvalidArgumentException(
                "No vector store resolver registered for driver [{$driver}]."
            );
        }

        return ($this->resolvers[$driver])($knowledgeBase, $options);
    }

    protected function registerDefaults(): void
    {
        $this->resolvers['file'] = function (KnowledgeBase $kb, array $options): VectorStoreInterface {
            $directory = (string) ($kb->vector_store_config['directory']
                ?? config('neuronai-studio.rag.storage_path', storage_path('app/neuronai-studio/rag')));

            return new StudioFileVectorStore(
                directory: $directory,
                topK: $this->topK($kb, $options),
                name: $kb->slug ?: (string) $kb->getKey(),
            );
        };

        $this->resolvers['memory'] = function (KnowledgeBase $kb, array $options): VectorStoreInterface {
            $cacheKey = (string) ($kb->getKey() ?? $kb->slug);

            if (! isset($this->memoryStores[$cacheKey])) {
                $this->memoryStores[$cacheKey] = new MemoryVectorStore(
                    topK: $this->topK($kb, $options),
                );
            }

            return $this->memoryStores[$cacheKey];
        };
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function topK(KnowledgeBase $kb, array $options): int
    {
        $topK = $options['top_k']
            ?? $kb->retrievalDefault('top_k', config('neuronai-studio.rag.retrieval.top_k', 5));

        return max(1, (int) $topK);
    }
}
