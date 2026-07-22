# Vector Stores

NeuronAI Studio registers first-party [Neuron AI vector stores](https://docs.neuron-ai.dev/rag/vector-store) in `VectorStoreFactory`. Each knowledge base picks a driver and stores connection fields in `vector_store_config`.

Secrets should use **env var names** (`key_env` / `api_key_env`), not raw API keys in the database.

## Driver matrix

| Driver | Extra Composer package | Main config fields |
|--------|------------------------|--------------------|
| `file` | — | optional `directory` (defaults to `rag.storage_path`) |
| `memory` | — | none (volatile; good for tests) |
| `pinecone` | — | `key_env`, `index_url`, `namespace` |
| `qdrant` | — | `collection_url`, `key_env`, `dimension` |
| `chroma` | — | `collection`, `host`, `key_env`, `tenant`, `database` |
| `weaviate` | — | `collection`, `host`, `key_env` |
| `meilisearch` | — | `index_uid`, `host`, `key_env`, `embedder`, `dimension` |
| `mariadb` | — (MariaDB ≥11.7) | `connection`, `table` |
| `elasticsearch` | `elasticsearch/elasticsearch` | `hosts`, `api_key_env`, `index` |
| `opensearch` | `opensearch-project/opensearch-php` | `base_uri`, `index` |
| `typesense` | `typesense/typesense-php` | `api_key_env`, nodes via `host`/`port`/`protocol`, `collection`, `vector_dimension` |
| `phpvector` | `neuron-core/php-vector` | `path` |

Optional packages are listed under `suggest` in the Studio `composer.json`. Selecting a driver without its client installed fails with a `composer require …` hint.

## MariaDB setup

Create a VECTOR table (adjust dimension to match your embedder):

```sql
CREATE TABLE IF NOT EXISTS rag_documents (
    id UUID NOT NULL PRIMARY KEY,
    content TEXT,
    sourceType VARCHAR(255),
    sourceName VARCHAR(255),
    metadata JSON,
    embedding VECTOR(1536) NOT NULL,
    VECTOR INDEX (embedding)
);
```

Point the knowledge base at your Laravel DB connection name and table.

## Custom drivers

Override or add drivers at boot:

```php
use DigitalElvis\NeuronAIStudio\Runtime\Rag\VectorStoreFactory;

app(VectorStoreFactory::class)->extend('my-store', function ($knowledgeBase, array $options) {
    return new \App\Neuron\MyVectorStore(/* ... */);
});
```

Then add an entry under `config('neuronai-studio.rag.vector_stores')` so it appears in the UI.

## Env reference

| Key | Env | Default |
|-----|-----|---------|
| `rag.default_vector_store` | `NEURONAI_STUDIO_RAG_VECTOR_STORE` | `file` |
| `rag.storage_path` | `NEURONAI_STUDIO_RAG_STORAGE_PATH` | `storage/app/neuronai-studio/rag` |
| `rag.documents_disk` | `NEURONAI_STUDIO_RAG_DOCUMENTS_DISK` | `local` |
| `rag.documents_path` | `NEURONAI_STUDIO_RAG_DOCUMENTS_PATH` | `neuronai-studio/knowledge-documents` |
| `rag.async_ingest` | `NEURONAI_STUDIO_RAG_ASYNC_INGEST` | `true` |

Full table: [Configuration — RAG](../../reference/configuration.md#rag).
