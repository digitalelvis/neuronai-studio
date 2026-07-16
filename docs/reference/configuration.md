# Configuration Reference

Complete reference for `config/neuronai-studio.php`. Publish with:

```bash
php artisan vendor:publish --tag=neuronai-studio-config
```

## Routing & auth

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `route_prefix` | `NEURONAI_STUDIO_ROUTE_PREFIX` | `neuronai-studio` | URL prefix for all studio routes |
| `table_prefix` | `NEURONAI_STUDIO_TABLE_PREFIX` | `neuronai_studio_` | Database table prefix |
| `middleware` | — | `['web', 'neuronai-studio.auth']` | Route middleware stack |
| `gate` | — | `viewNeuronAIStudio` | Authorization gate name |

## Export

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `export_namespace` | `NEURONAI_STUDIO_EXPORT_NAMESPACE` | `App\Neuron` | PHP namespace for exported classes |
| `export_path` | `NEURONAI_STUDIO_EXPORT_PATH` | `app/Neuron` | Export directory |

## AI providers

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `providers` | — | openai, anthropic, gemini, ollama | Provider/model picker options |
| `default_provider` | `NEURONAI_STUDIO_DEFAULT_PROVIDER` | `openai` | Default provider in forms |
| `default_model` | `NEURONAI_STUDIO_DEFAULT_MODEL` | `gpt-4o-mini` | Default model in forms |

Credentials are **not** stored here — they come from `config/neuron.php`.

## Usage & cost estimation

Approximate prices for `estimated_cost` on LLM spans and runs. Values are **estimates, not provider invoices**. Rates are per **1k tokens** in the install currency.

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `usage.currency` | `NEURONAI_STUDIO_USAGE_CURRENCY` | `USD` | Single currency for all estimates |
| `usage.pricing` | — | catalog model map | `provider` → `model` → `{ prompt_per_1k, completion_per_1k }` |
| `usage.export.enabled` | `NEURONAI_STUDIO_USAGE_EXPORT_ENABLED` | `true` | Stub for usage-export-api |
| `usage.export.route_prefix` | `NEURONAI_STUDIO_USAGE_EXPORT_PREFIX` | `null` | Optional export route prefix |
| `usage.export.middleware` | — | `null` | Optional export middleware |
| `usage.events.enabled` | `NEURONAI_STUDIO_USAGE_EVENTS_ENABLED` | `false` | Stub for usage events |

Lookup is exact-match on the span's `provider` + `model`. Missing keys → cost `0`. Ollama catalog entries default to `0`.

Override after publishing config:

```php
'usage' => [
    'currency' => 'USD',
    'pricing' => [
        'openai' => [
            'gpt-4o-mini' => [
                'prompt_per_1k' => 0.00015,
                'completion_per_1k' => 0.0006,
            ],
        ],
    ],
],
```

See [Cost estimation](../guides/analytics/costs.md).

## Chat history

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `chat_history_context_window` | `NEURONAI_STUDIO_CHAT_HISTORY_CONTEXT_WINDOW` | `150000` | Max tokens loaded for agent threads |

## Queue

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `async_runs_enabled` | `NEURONAI_STUDIO_ASYNC_RUNS_ENABLED` | `false` | Enable async workflow runs via queue jobs (SSE harness remains default when false) |
| `queue` | `NEURONAI_STUDIO_QUEUE` | `default` | Queue name for `RunWorkflowJob` and `ResumeWorkflowJob` |
| `queue_connection` | `NEURONAI_STUDIO_QUEUE_CONNECTION` | `null` | Queue connection override |
| `queue_tries` | `NEURONAI_STUDIO_QUEUE_TRIES` | `1` | Max attempts for workflow queue jobs |
| `queue_backoff` | `NEURONAI_STUDIO_QUEUE_BACKOFF` | `30` | Seconds before retry after failure |

## Inspector

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `inspector_enabled` | `NEURONAI_STUDIO_INSPECTOR_ENABLED` | `false` | Inspector APM integration (reserved) |

## Tools

| Key | Default | Description |
|-----|---------|-------------|
| `tools` | calculator, calendar | Built-in toolkit registry |
| `tool_scan_paths` | `app/Neuron/Tools` | Paths to scan for PHP Tool classes |

## Structured output

| Key | Default | Description |
|-----|---------|-------------|
| `structured_output_scan_paths` | `{export_path}/Output` when directory exists, else `[]` | Paths to scan for PHP output classes with `SchemaProperty` attributes |

Classes discovered here populate the **Output class** dropdown on Agent and LLM nodes in the workflow canvas. Each path can be absolute or relative to the application base path.

Default behavior:

```php
'structured_output_scan_paths' => is_dir($exportPath.'/Output')
    ? [$exportPath.'/Output']
    : [],
```

Add extra scan paths when output classes live outside the export directory:

```php
'structured_output_scan_paths' => [
    app_path('Neuron/Output'),
    app_path('DTOs/AgentOutput'),
],
```

Classes must have public properties annotated with `NeuronAI\StructuredOutput\SchemaProperty`. Abstract classes and classes without schema properties are ignored.

## Workflows

| Key | Default | Description |
|-----|---------|-------------|
| `workflow_scan_paths` | `app/Neuron`, `app/Neuron/Workflows` | PHP workflow class scan paths |
| `workflow_json_paths` | `workflows/` | JSON workflow import paths |

### Loop defaults

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `loop.default_max_steps` | — | `10` | Default `max_steps` when a Loop node omits the field |
| `loop.global_max_steps` | — | `1000` | Hard cap on total node executions per run |

### Checkpoints

Opt-in per-node result cache used to skip re-executing expensive nodes on resume (set
`data.checkpoint: true` on Agent/LLM/RAG/Tool nodes). See
[Node checkpoints](../guides/workflows/runtime-and-traces.md#node-checkpoints).

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `checkpoints.enabled` | `NEURONAI_STUDIO_CHECKPOINTS_ENABLED` | `true` | Global switch for node checkpointing |
| `checkpoints.ttl` | `NEURONAI_STUDIO_CHECKPOINTS_TTL` | `null` | Seconds before a checkpoint expires (`null` = never) |

Purge expired checkpoints:

```bash
php artisan neuronai-studio:checkpoints:purge
```

## Templates

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `templates_enabled` | `NEURONAI_STUDIO_TEMPLATES_ENABLED` | `true` | Enable template browser |
| `template_paths` | — | package `resources/templates/` | Agent/workflow template directories |

## MCP

| Key | Default | Description |
|-----|---------|-------------|
| `mcp_servers` | filesystem, telescope | Config preset MCP servers |
| `mcp_stdio_allowlist` | npx, node, python, etc. | Allowed stdio commands |

## Webhooks

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `webhook_allowed_hosts` | `NEURONAI_STUDIO_WEBHOOK_ALLOWED_HOSTS` | `*` | Host allowlist for webhook tools |
| `webhook_timeout` | `NEURONAI_STUDIO_WEBHOOK_TIMEOUT` | `15` | Request timeout in seconds |

## Node types

| Key | Description |
|-----|-------------|
| `node_types` | Metadata (label, icon, category) for canvas palette |

## Attachments

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `attachments.disk` | `NEURONAI_STUDIO_ATTACHMENTS_DISK` | `local` | Storage disk |
| `attachments.path` | `NEURONAI_STUDIO_ATTACHMENTS_PATH` | `neuronai-studio/attachments` | Storage path |
| `attachments.max_size_kb` | `NEURONAI_STUDIO_ATTACHMENTS_MAX_KB` | `10240` | Max upload size |
| `attachments.allowed_mimes` | — | images, audio, video, pdf, text | Allowed MIME types |

In workflow runs, attachments uploaded in the test harness are stored in `state.attachments` and passed to Agent/LLM nodes via `MessageFactory`. The same array persists across loop iterations for autonomous agent patterns.

## RAG

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `rag.default_vector_store` | `NEURONAI_STUDIO_RAG_VECTOR_STORE` | `file` | Default vector store driver |
| `rag.storage_path` | `NEURONAI_STUDIO_RAG_STORAGE_PATH` | `storage/app/neuronai-studio/rag` | Root path for file-based stores |
| `rag.default_embeddings_provider` | `NEURONAI_STUDIO_RAG_EMBEDDINGS_PROVIDER` | `openai` | Default embeddings provider |
| `rag.default_embeddings_model` | `NEURONAI_STUDIO_RAG_EMBEDDINGS_MODEL` | `text-embedding-3-small` | Default embeddings model |
| `rag.retrieval.top_k` | `NEURONAI_STUDIO_RAG_TOP_K` | `5` | Default chunks to retrieve |
| `rag.retrieval.threshold` | `NEURONAI_STUDIO_RAG_THRESHOLD` | `null` | Default minimum similarity score |
| `rag.chunk.max_words` | `NEURONAI_STUDIO_RAG_CHUNK_MAX_WORDS` | `200` | Ingest chunk size |
| `rag.chunk.overlap_words` | `NEURONAI_STUDIO_RAG_CHUNK_OVERLAP_WORDS` | `20` | Chunk overlap |

Register custom vector stores or embeddings providers at runtime:

```php
use DigitalElvis\NeuronAIStudio\Runtime\Rag\VectorStoreFactory;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\EmbeddingsFactory;

VectorStoreFactory::extend('pinecone', fn () => /* ... */);
EmbeddingsFactory::extend('custom', fn () => /* ... */);
```

Each knowledge base may override provider, model, and vector store driver independently of these defaults.

## See also

- [Cost estimation](../guides/analytics/costs.md)
- [Database schema](database-schema.md)
- [Publish Tags](publish-tags.md)
- [Security & Access](../guides/security-and-access.md)
