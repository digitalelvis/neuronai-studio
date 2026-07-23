<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Rag;

use Closure;
use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use NeuronAI\RAG\VectorStore\ChromaVectorStore;
use NeuronAI\RAG\VectorStore\ElasticsearchVectorStore;
use NeuronAI\RAG\VectorStore\MariaDBVectorStore;
use NeuronAI\RAG\VectorStore\MeilisearchVectorStore;
use NeuronAI\RAG\VectorStore\MemoryVectorStore;
use NeuronAI\RAG\VectorStore\OpenSearchVectorStore;
use NeuronAI\RAG\VectorStore\PineconeVectorStore;
use NeuronAI\RAG\VectorStore\QdrantVectorStore;
use NeuronAI\RAG\VectorStore\TypesenseVectorStore;
use NeuronAI\RAG\VectorStore\VectorStoreInterface;
use NeuronAI\RAG\VectorStore\WeaviateVectorStore;
use PDO;

/**
 * Resolves a vector store for a knowledge base. Built-in drivers mirror Neuron AI
 * first-party stores; optional clients (Elasticsearch, OpenSearch, Typesense,
 * PHPVector) fail with a clear composer require hint when missing.
 *
 * Host apps can still override or add drivers via {@see VectorStoreFactory::extend()}.
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
     * @return list<string>
     */
    public function drivers(): array
    {
        return array_keys($this->resolvers);
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

    /**
     * Absolute path to the file-backed store for a knowledge base (if using file driver).
     */
    public function fileStorePath(KnowledgeBase $knowledgeBase): string
    {
        $directory = (string) ($knowledgeBase->vector_store_config['directory']
            ?? config('neuronai-studio.rag.storage_path', storage_path('app/neuronai-studio/rag')));
        $name = $knowledgeBase->slug ?: (string) $knowledgeBase->getKey();

        return rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name.'.store';
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

        $this->resolvers['pinecone'] = function (KnowledgeBase $kb, array $options): VectorStoreInterface {
            $cfg = $this->storeConfig($kb);

            return new PineconeVectorStore(
                key: $this->resolveEnv($cfg, 'key_env', 'PINECONE_API_KEY'),
                indexUrl: $this->requireString($cfg, 'index_url', 'pinecone'),
                topK: $this->topK($kb, $options),
                namespace: (string) ($cfg['namespace'] ?? '__default__'),
            );
        };

        $this->resolvers['qdrant'] = function (KnowledgeBase $kb, array $options): VectorStoreInterface {
            $cfg = $this->storeConfig($kb);
            $key = $this->optionalEnv($cfg, 'key_env', 'QDRANT_API_KEY');

            return new QdrantVectorStore(
                collectionUrl: $this->requireString($cfg, 'collection_url', 'qdrant'),
                key: $key !== '' ? $key : null,
                topK: $this->topK($kb, $options),
                dimension: (int) ($cfg['dimension'] ?? 1024),
            );
        };

        $this->resolvers['chroma'] = function (KnowledgeBase $kb, array $options): VectorStoreInterface {
            $cfg = $this->storeConfig($kb);
            $key = $this->optionalEnv($cfg, 'key_env', 'CHROMA_API_KEY');

            return new ChromaVectorStore(
                collection: $this->requireString($cfg, 'collection', 'chroma'),
                host: (string) ($cfg['host'] ?? 'http://localhost:8000'),
                tenant: (string) ($cfg['tenant'] ?? 'default_tenant'),
                database: (string) ($cfg['database'] ?? 'default_database'),
                key: $key !== '' ? $key : null,
                topK: $this->topK($kb, $options),
            );
        };

        $this->resolvers['weaviate'] = function (KnowledgeBase $kb, array $options): VectorStoreInterface {
            $cfg = $this->storeConfig($kb);
            $key = $this->optionalEnv($cfg, 'key_env', 'WEAVIATE_API_KEY');

            return new WeaviateVectorStore(
                collection: $this->requireString($cfg, 'collection', 'weaviate'),
                host: (string) ($cfg['host'] ?? 'http://localhost:8080'),
                key: $key !== '' ? $key : null,
                topK: $this->topK($kb, $options),
            );
        };

        $this->resolvers['meilisearch'] = function (KnowledgeBase $kb, array $options): VectorStoreInterface {
            $cfg = $this->storeConfig($kb);
            $key = $this->optionalEnv($cfg, 'key_env', 'MEILISEARCH_API_KEY');

            return new MeilisearchVectorStore(
                indexUid: $this->requireString($cfg, 'index_uid', 'meilisearch'),
                host: (string) ($cfg['host'] ?? 'http://localhost:7700'),
                key: $key !== '' ? $key : null,
                embedder: (string) ($cfg['embedder'] ?? 'default'),
                topK: $this->topK($kb, $options),
                dimension: (int) ($cfg['dimension'] ?? 1024),
            );
        };

        $this->resolvers['mariadb'] = function (KnowledgeBase $kb, array $options): VectorStoreInterface {
            $cfg = $this->storeConfig($kb);
            $connection = (string) ($cfg['connection'] ?? config('database.default'));

            return new MariaDBVectorStore(
                pdo: $this->pdo($connection),
                tableName: (string) ($cfg['table'] ?? 'rag_documents'),
                topK: $this->topK($kb, $options),
            );
        };

        $this->resolvers['elasticsearch'] = function (KnowledgeBase $kb, array $options): VectorStoreInterface {
            $this->requirePackage(
                'Elastic\\Elasticsearch\\ClientBuilder',
                'elasticsearch/elasticsearch',
                'elasticsearch',
            );

            $cfg = $this->storeConfig($kb);
            $hosts = $cfg['hosts'] ?? ['http://localhost:9200'];

            if (is_string($hosts)) {
                $hosts = array_values(array_filter(array_map('trim', explode(',', $hosts))));
            }

            $builder = \Elastic\Elasticsearch\ClientBuilder::create()
                ->setHosts(array_values((array) $hosts));

            $apiKey = $this->optionalEnv($cfg, 'api_key_env', 'ELASTICSEARCH_API_KEY');

            if ($apiKey !== '') {
                $builder->setApiKey($apiKey);
            }

            return new ElasticsearchVectorStore(
                client: $builder->build(),
                index: (string) ($cfg['index'] ?? 'neuron-ai'),
                topK: $this->topK($kb, $options),
            );
        };

        $this->resolvers['opensearch'] = function (KnowledgeBase $kb, array $options): VectorStoreInterface {
            $this->requirePackage(
                'OpenSearch\\GuzzleClientFactory',
                'opensearch-project/opensearch-php',
                'opensearch',
            );

            $cfg = $this->storeConfig($kb);
            $client = (new \OpenSearch\GuzzleClientFactory)->create([
                'base_uri' => (string) ($cfg['base_uri'] ?? 'http://localhost:9200'),
            ]);

            return new OpenSearchVectorStore(
                client: $client,
                index: (string) ($cfg['index'] ?? 'neuron-ai'),
                topK: $this->topK($kb, $options),
            );
        };

        $this->resolvers['typesense'] = function (KnowledgeBase $kb, array $options): VectorStoreInterface {
            $this->requirePackage(
                'Typesense\\Client',
                'typesense/typesense-php',
                'typesense',
            );

            $cfg = $this->storeConfig($kb);
            $nodes = $cfg['nodes'] ?? [[
                'host' => (string) ($cfg['host'] ?? 'localhost'),
                'port' => (string) ($cfg['port'] ?? '8108'),
                'protocol' => (string) ($cfg['protocol'] ?? 'http'),
            ]];

            if (is_string($nodes)) {
                $decoded = json_decode($nodes, true);
                $nodes = is_array($decoded) ? $decoded : [];
            }

            $client = new \Typesense\Client([
                'api_key' => $this->resolveEnv($cfg, 'api_key_env', 'TYPESENSE_API_KEY'),
                'nodes' => array_values((array) $nodes),
            ]);

            return new TypesenseVectorStore(
                client: $client,
                collection: (string) ($cfg['collection'] ?? 'neuron-ai'),
                vectorDimension: (int) ($cfg['vector_dimension'] ?? 1024),
                topK: (string) $this->topK($kb, $options),
            );
        };

        $this->resolvers['phpvector'] = function (KnowledgeBase $kb, array $options): VectorStoreInterface {
            $this->requirePackage(
                'NeuronAI\\PHPVector\\PHPVector',
                'neuron-core/php-vector',
                'phpvector',
            );

            $cfg = $this->storeConfig($kb);
            $path = (string) ($cfg['path']
                ?? (config('neuronai-studio.rag.storage_path', storage_path('app/neuronai-studio/rag'))
                    .DIRECTORY_SEPARATOR.'phpvector'
                    .DIRECTORY_SEPARATOR.($kb->slug ?: (string) $kb->getKey())));

            return new \NeuronAI\PHPVector\PHPVector(
                path: $path,
                topK: $this->topK($kb, $options),
            );
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function storeConfig(KnowledgeBase $kb): array
    {
        return (array) ($kb->vector_store_config ?? []);
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    protected function requireString(array $cfg, string $key, string $driver): string
    {
        $value = trim((string) ($cfg[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException(
                "Vector store [{$driver}] requires vector_store_config.{$key}."
            );
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    protected function resolveEnv(array $cfg, string $key, string $defaultEnv): string
    {
        $envName = (string) ($cfg[$key] ?? $defaultEnv);
        $value = (string) (env($envName) ?? '');

        if ($value === '' && isset($cfg['api_key'])) {
            $value = (string) $cfg['api_key'];
        }

        if ($value === '' && isset($cfg['key'])) {
            $value = (string) $cfg['key'];
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    protected function optionalEnv(array $cfg, string $key, string $defaultEnv): string
    {
        return $this->resolveEnv($cfg, $key, $defaultEnv);
    }

    protected function requirePackage(string $class, string $package, string $driver): void
    {
        if (class_exists($class)) {
            return;
        }

        throw new InvalidArgumentException(
            "Vector store [{$driver}] requires the optional package [{$package}]. Run: composer require {$package}"
        );
    }

    protected function pdo(string $connection): PDO
    {
        $pdo = DB::connection($connection)->getPdo();

        if (! $pdo instanceof PDO) {
            throw new InvalidArgumentException(
                "Database connection [{$connection}] does not expose a PDO instance for MariaDB vector store."
            );
        }

        return $pdo;
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
