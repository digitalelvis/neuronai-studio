<?php

$exportPath = env('NEURONAI_STUDIO_EXPORT_PATH', app_path('Neuron'));

return [

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    */

    'route_prefix' => env('NEURONAI_STUDIO_ROUTE_PREFIX', 'neuronai-studio'),

    'table_prefix' => env('NEURONAI_STUDIO_TABLE_PREFIX', 'neuronai_studio_'),

    'middleware' => ['web', 'neuronai-studio.auth'],

    'gate' => 'viewNeuronAIStudio',

    /*
    |--------------------------------------------------------------------------
    | Stream Adapters (External Integration)
    |--------------------------------------------------------------------------
    |
    | Exposes agents and workflows to external clients (Vercel AI SDK, AG-UI,
    | ...) via dedicated streaming endpoints. These routes are completely
    | separate from the internal Studio playground/harness and only registered
    | when `enabled` is true. The host app controls prefix and middleware
    | (e.g. ['api', 'auth:sanctum']) independently of the Studio UI middleware.
    |
    */

    'stream_adapters' => [
        'enabled' => env('NEURONAI_STUDIO_INTEGRATE_ENABLED', true),
        'route_prefix' => env('NEURONAI_STUDIO_INTEGRATE_PREFIX', 'api/neuronai'),
        'middleware' => ['api'],
        'protocols' => [
            'vercel' => ['enabled' => true],
            'agui' => ['enabled' => true],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Configuration
    |--------------------------------------------------------------------------
    */

    'export_namespace' => env('NEURONAI_STUDIO_EXPORT_NAMESPACE', 'App\\Neuron'),

    'export_path' => $exportPath,

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Available providers in the UI. Credentials come from config/neuron.php.
    |
    */

    'providers' => [
        'openai' => [
            'label' => 'OpenAI',
            'models' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo'],
        ],
        'anthropic' => [
            'label' => 'Anthropic',
            'models' => ['claude-sonnet-4-20250514', 'claude-3-5-sonnet-20241022'],
        ],
        'gemini' => [
            'label' => 'Gemini',
            'models' => [
                'gemini-3.5-flash',
                'gemini-3.1-pro-preview',
                'gemini-3.1-pro-preview-customtools',
                'gemini-3-flash-preview',
                'gemini-3.1-flash-lite',
                'gemini-2.5-pro',
                'gemini-2.5-flash',
                'gemini-2.5-flash-lite',
            ],
        ],
        'ollama' => [
            'label' => 'Ollama',
            'models' => ['llama3.2', 'mistral'],
        ],
    ],

    'default_provider' => env('NEURONAI_STUDIO_DEFAULT_PROVIDER', 'openai'),

    'default_model' => env('NEURONAI_STUDIO_DEFAULT_MODEL', 'gpt-4o-mini'),

    /*
    |--------------------------------------------------------------------------
    | Usage & Cost Estimation
    |--------------------------------------------------------------------------
    |
    | Approximate public list prices for estimated_cost (not invoices). Rates are
    | USD per 1k tokens: cost ≈ (prompt/1000)*prompt_per_1k +
    | (completion/1000)*completion_per_1k. Override after publishing this config.
    | Missing provider/model → cost 0. Ollama defaults to 0 (local).
    |
    | Export routes register when usage.export.enabled is true, independently of
    | stream_adapters.enabled. Null route_prefix / middleware fall back to
    | stream_adapters.route_prefix / stream_adapters.middleware so the host can
    | share one gate without duplicating values.
    |
    */

    'usage' => [
        'currency' => env('NEURONAI_STUDIO_USAGE_CURRENCY', 'USD'),

        'pricing' => [
            'openai' => [
                'gpt-4o-mini' => ['prompt_per_1k' => 0.00015, 'completion_per_1k' => 0.0006],
                'gpt-4o' => ['prompt_per_1k' => 0.0025, 'completion_per_1k' => 0.01],
                'gpt-4-turbo' => ['prompt_per_1k' => 0.01, 'completion_per_1k' => 0.03],
            ],
            'anthropic' => [
                'claude-sonnet-4-20250514' => ['prompt_per_1k' => 0.003, 'completion_per_1k' => 0.015],
                'claude-3-5-sonnet-20241022' => ['prompt_per_1k' => 0.003, 'completion_per_1k' => 0.015],
            ],
            'gemini' => [
                'gemini-3.5-flash' => ['prompt_per_1k' => 0.0015, 'completion_per_1k' => 0.009],
                'gemini-3.1-pro-preview' => ['prompt_per_1k' => 0.002, 'completion_per_1k' => 0.012],
                'gemini-3.1-pro-preview-customtools' => ['prompt_per_1k' => 0.002, 'completion_per_1k' => 0.012],
                'gemini-3-flash-preview' => ['prompt_per_1k' => 0.0005, 'completion_per_1k' => 0.003],
                'gemini-3.1-flash-lite' => ['prompt_per_1k' => 0.00025, 'completion_per_1k' => 0.0015],
                'gemini-2.5-pro' => ['prompt_per_1k' => 0.00125, 'completion_per_1k' => 0.01],
                'gemini-2.5-flash' => ['prompt_per_1k' => 0.0003, 'completion_per_1k' => 0.0025],
                'gemini-2.5-flash-lite' => ['prompt_per_1k' => 0.0001, 'completion_per_1k' => 0.0004],
            ],
            'ollama' => [
                'llama3.2' => ['prompt_per_1k' => 0, 'completion_per_1k' => 0],
                'mistral' => ['prompt_per_1k' => 0, 'completion_per_1k' => 0],
            ],
        ],

        'export' => [
            // Independent of stream_adapters.enabled — host can export without stream protocols.
            'enabled' => env('NEURONAI_STUDIO_USAGE_EXPORT_ENABLED', true),
            // null ⇒ stream_adapters.route_prefix (default api/neuronai)
            'route_prefix' => env('NEURONAI_STUDIO_USAGE_EXPORT_PREFIX'),
            // null ⇒ stream_adapters.middleware (default ['api']); host owns auth.
            'middleware' => null,
        ],

        'events' => [
            // Dispatch RunUsageRecorded on terminal runs when true (separate from export HTTP).
            'enabled' => env('NEURONAI_STUDIO_USAGE_EVENTS_ENABLED', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Chat History
    |--------------------------------------------------------------------------
    |
    | Context window for persisted agent test threads. Set ~5-10% below the
    | model limit to leave room for the system prompt and tool payloads.
    |
    */

    'chat_history_context_window' => (int) env('NEURONAI_STUDIO_CHAT_HISTORY_CONTEXT_WINDOW', 150000),

    /*
    |--------------------------------------------------------------------------
    | Memory / summarization
    |--------------------------------------------------------------------------
    |
    | Optional dedicated cheap model for history compaction. When unset, the
    | agent's own provider/model is used. Failures degrade to non-destructive
    | trim (see agent-memory-controls).
    |
    */

    'memory' => [
        'summarizer' => [
            'provider' => env('NEURONAI_STUDIO_SUMMARIZER_PROVIDER'),
            'model' => env('NEURONAI_STUDIO_SUMMARIZER_MODEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */

    'queue' => env('NEURONAI_STUDIO_QUEUE', 'default'),

    'queue_connection' => env('NEURONAI_STUDIO_QUEUE_CONNECTION'),

    'async_runs_enabled' => env('NEURONAI_STUDIO_ASYNC_RUNS_ENABLED', false),

    'queue_tries' => (int) env('NEURONAI_STUDIO_QUEUE_TRIES', 1),

    'queue_backoff' => (int) env('NEURONAI_STUDIO_QUEUE_BACKOFF', 30),

    /*
    |--------------------------------------------------------------------------
    | Async run progress (SSE tail without Echo)
    |--------------------------------------------------------------------------
    */

    'async_progress' => [
        'enabled' => (bool) env('NEURONAI_STUDIO_ASYNC_PROGRESS_ENABLED', true),
        'ttl' => (int) env('NEURONAI_STUDIO_ASYNC_PROGRESS_TTL', 3600),
        'poll_ms' => (int) env('NEURONAI_STUDIO_ASYNC_PROGRESS_POLL_MS', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Parallel (interpreted fork/join)
    |--------------------------------------------------------------------------
    */

    'parallel' => [
        // sequential | concurrent (Amp fibers when amphp/amp is available)
        'concurrency' => env('NEURONAI_STUDIO_PARALLEL_CONCURRENCY', 'concurrent'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Checkpoints & Persistence
    |--------------------------------------------------------------------------
    |
    | Node-level checkpoints let opt-in nodes (rag, llm, agent, tool with
    | `checkpoint: true`) skip re-execution when a workflow resumes. Results are
    | keyed by trace + node + loop iteration and persisted to the
    | `neuronai_studio_workflow_checkpoints` table. `ttl` (minutes, null = keep
    | forever) drives the `neuronai-studio:checkpoints:purge` command.
    |
    */

    'checkpoints' => [
        'enabled' => (bool) env('NEURONAI_STUDIO_CHECKPOINTS_ENABLED', true),
        'ttl' => env('NEURONAI_STUDIO_CHECKPOINTS_TTL') !== null
            ? (int) env('NEURONAI_STUDIO_CHECKPOINTS_TTL')
            : null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Observability (native Debugger + external monitoring)
    |--------------------------------------------------------------------------
    |
    | Env-first: enabled=true means "auto when credentials/package present".
    | Set enabled=false to force an integration off even if keys exist.
    |
    */

    'observability' => [
        'native_tracing' => (bool) env('NEURONAI_STUDIO_NATIVE_TRACING', true),

        'inspector' => [
            'enabled' => (bool) env('NEURONAI_STUDIO_INSPECTOR_ENABLED', true),
        ],

        'langfuse' => [
            'enabled' => (bool) env('NEURONAI_STUDIO_LANGFUSE_ENABLED', true),
            'public_key' => env('LANGFUSE_PUBLIC_KEY'),
            'secret_key' => env('LANGFUSE_SECRET_KEY'),
            'base_url' => env('LANGFUSE_BASE_URL', env('LANGFUSE_HOST')),
        ],

        'metadata' => [],
        'tags' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Inspector Integration (deprecated alias)
    |--------------------------------------------------------------------------
    |
    | Prefer observability.inspector.enabled. Kept for one release.
    |
    */

    'inspector_enabled' => env('NEURONAI_STUDIO_INSPECTOR_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Tools & Toolkits
    |--------------------------------------------------------------------------
    |
    | Built-in toolkits available in the studio UI. Credentials use env:KEY syntax.
    |
    */

    'tools' => [
        'calculator' => [
            'type' => 'toolkit',
            'class' => \NeuronAI\Tools\Toolkits\Calculator\CalculatorToolkit::class,
            'label' => 'Calculator',
            'category' => 'builtin',
            'description' => 'Mathematical operations: sum, subtract, multiply, divide, statistics, etc.',
        ],
        'calendar' => [
            'type' => 'toolkit',
            'class' => \NeuronAI\Tools\Toolkits\Calendar\CalendarToolkit::class,
            'label' => 'Calendar',
            'category' => 'builtin',
            'description' => 'Date and time operations, formatting, timezone conversions.',
        ],
    ],

    'tool_scan_paths' => [
        app_path('Neuron/Tools'),
    ],

    'structured_output_scan_paths' => is_dir($exportPath.DIRECTORY_SEPARATOR.'Output')
        ? [$exportPath.DIRECTORY_SEPARATOR.'Output']
        : [],

    'workflow_scan_paths' => [
        app_path('Neuron'),
        app_path('Neuron/Workflows'),
    ],

    'workflow_json_paths' => [
        base_path('workflows'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Templates
    |--------------------------------------------------------------------------
    */

    'templates_enabled' => env('NEURONAI_STUDIO_TEMPLATES_ENABLED', true),

    // Package built-in templates always load from the package source. These paths
    // are optional extras (e.g. app-specific templates in resources/templates/).
    'template_paths' => [
        'agent' => env('NEURONAI_STUDIO_AGENT_TEMPLATE_PATH'),
        'workflow' => env('NEURONAI_STUDIO_WORKFLOW_TEMPLATE_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Servers
    |--------------------------------------------------------------------------
    |
    | MCP connectors exposed in the studio. Credentials must use env: references.
    |
    */

    'mcp_servers' => [
        'filesystem' => [
            'label' => 'Filesystem',
            'description' => 'Read and write files via MCP stdio server.',
            'transport' => 'stdio',
            'command' => 'npx',
            'args' => ['-y', '@modelcontextprotocol/server-filesystem', storage_path('app')],
        ],
        'telescope' => [
            'label' => 'Telescope',
            'description' => 'Query Laravel Telescope monitoring data via HTTP MCP.',
            'transport' => 'http',
            'url' => env('TELESCOPE_MCP_URL', 'http://127.0.0.1:8000/telescope/mcp'),
            'token_env' => 'TELESCOPE_MCP_TOKEN',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Stdio Allowlist
    |--------------------------------------------------------------------------
    |
    | Allowed stdio commands for DB-managed MCP servers. Empty = allow all.
    |
    */

    'mcp_stdio_allowlist' => [
        'npx',
        'node',
        'python',
        'python3',
        'uv',
        'uvx',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Tool Security
    |--------------------------------------------------------------------------
    */

    'webhook_allowed_hosts' => env('NEURONAI_STUDIO_WEBHOOK_ALLOWED_HOSTS', '*'),

    'webhook_timeout' => (int) env('NEURONAI_STUDIO_WEBHOOK_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Node Types
    |--------------------------------------------------------------------------
    */

    'node_types' => [
        'start' => [
            'label' => 'Start',
            'icon' => 'play',
            'category' => 'flow',
        ],
        'stop' => [
            'label' => 'Stop',
            'icon' => 'stop',
            'category' => 'flow',
        ],
        'agent' => [
            'label' => 'Agent',
            'icon' => 'bot',
            'category' => 'ai',
        ],
        'llm' => [
            'label' => 'LLM',
            'icon' => 'message-square',
            'category' => 'ai',
        ],
        'condition' => [
            'label' => 'Condition',
            'icon' => 'git-branch',
            'category' => 'logic',
        ],
        'set_state' => [
            'label' => 'Set State',
            'icon' => 'database',
            'category' => 'logic',
        ],
        'tool' => [
            'label' => 'Tool',
            'icon' => 'wrench',
            'category' => 'ai',
        ],
        'rag' => [
            'label' => 'RAG',
            'icon' => 'search',
            'category' => 'ai',
        ],
        'delay' => [
            'label' => 'Delay',
            'icon' => 'clock',
            'category' => 'flow',
        ],
        'mcp' => [
            'label' => 'MCP',
            'icon' => 'plug',
            'category' => 'ai',
        ],
        'human' => [
            'label' => 'Human',
            'icon' => 'message-square',
            'category' => 'flow',
        ],
        'loop' => [
            'label' => 'Loop',
            'icon' => 'repeat',
            'category' => 'logic',
        ],
        'fork' => [
            'label' => 'Fork',
            'icon' => 'git-fork',
            'category' => 'logic',
        ],
        'join' => [
            'label' => 'Join',
            'icon' => 'git-merge',
            'category' => 'logic',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Loop / Cyclic Graph Defaults
    |--------------------------------------------------------------------------
    */

    'loop' => [
        'default_max_steps' => (int) env('NEURONAI_STUDIO_LOOP_DEFAULT_MAX_STEPS', 10),
        'global_max_steps' => (int) env('NEURONAI_STUDIO_LOOP_GLOBAL_MAX_STEPS', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | RAG (Knowledge Bases & Retrieval)
    |--------------------------------------------------------------------------
    |
    | Drivers are pluggable: the packaged default is the zero-infra file store.
    | Register extra vector stores or embeddings providers at runtime via
    | VectorStoreFactory::extend() / EmbeddingsFactory::extend(), or override
    | the lists below. Every knowledge base may pick its own driver/model, so
    | these values only act as defaults.
    |
    */

    'rag' => [

        // Vector store driver used when a knowledge base does not specify one.
        'default_vector_store' => env('NEURONAI_STUDIO_RAG_VECTOR_STORE', 'file'),

        // Root directory for file-based vector stores (one file per knowledge base).
        'storage_path' => env('NEURONAI_STUDIO_RAG_STORAGE_PATH', storage_path('app/neuronai-studio/rag')),

        // Vector store drivers surfaced in the UI. Built-in resolvers: file, memory.
        // Additional drivers (pinecone, qdrant, chroma, pgvector, ...) can be enabled
        // by registering a resolver via VectorStoreFactory::extend().
        'vector_stores' => [
            'file' => [
                'label' => 'File (local disk)',
                'description' => 'Persists embeddings to disk. Zero infra, ideal for local/dev.',
            ],
            'memory' => [
                'label' => 'In-memory',
                'description' => 'Volatile store, resets each request. Useful for tests.',
            ],
        ],

        // Embeddings provider used when a knowledge base does not specify one.
        'default_embeddings_provider' => env('NEURONAI_STUDIO_RAG_EMBEDDINGS_PROVIDER', 'openai'),

        // Model used when a knowledge base (and its provider) does not specify one.
        'default_embeddings_model' => env('NEURONAI_STUDIO_RAG_EMBEDDINGS_MODEL', 'text-embedding-3-small'),

        // Embeddings providers surfaced in the UI. `key_env` resolves the API key
        // (falls back to config/neuron.php). `models` is the predefined list; devs
        // may always type a custom model or register a custom provider resolver.
        'embeddings' => [
            'openai' => [
                'label' => 'OpenAI',
                'key_env' => 'OPENAI_API_KEY',
                'default_model' => 'text-embedding-3-small',
                'dimensions' => (int) env('NEURONAI_STUDIO_RAG_OPENAI_DIMENSIONS', 1536),
                'models' => [
                    'text-embedding-3-small',
                    'text-embedding-3-large',
                    'text-embedding-ada-002',
                ],
            ],
            'gemini' => [
                'label' => 'Gemini',
                'key_env' => 'GEMINI_API_KEY',
                'default_model' => 'text-embedding-004',
                'models' => [
                    'text-embedding-004',
                    'gemini-embedding-001',
                ],
            ],
            'ollama' => [
                'label' => 'Ollama',
                'url' => env('OLLAMA_URL', 'http://localhost:11434/api'),
                'default_model' => 'nomic-embed-text',
                'models' => [
                    'nomic-embed-text',
                    'mxbai-embed-large',
                    'all-minilm',
                ],
            ],
            'voyage' => [
                'label' => 'Voyage',
                'key_env' => 'VOYAGE_API_KEY',
                'default_model' => 'voyage-3-lite',
                'models' => [
                    'voyage-3',
                    'voyage-3-lite',
                    'voyage-finance-2',
                ],
            ],
            'cohere' => [
                'label' => 'Cohere',
                'key_env' => 'COHERE_API_KEY',
                'default_model' => 'embed-multilingual-v3.0',
                'models' => [
                    'embed-english-v3.0',
                    'embed-multilingual-v3.0',
                ],
            ],
            'mistral' => [
                'label' => 'Mistral',
                'key_env' => 'MISTRAL_API_KEY',
                'default_model' => 'mistral-embed',
                'models' => [
                    'mistral-embed',
                ],
            ],
        ],

        // Default retrieval strategy applied when a knowledge base / node omits it.
        'retrieval' => [
            'top_k' => (int) env('NEURONAI_STUDIO_RAG_TOP_K', 5),
            'threshold' => env('NEURONAI_STUDIO_RAG_THRESHOLD') !== null
                ? (float) env('NEURONAI_STUDIO_RAG_THRESHOLD')
                : null,
        ],

        // Default chunking strategy for ingestion (word-based sentence splitter).
        'chunk' => [
            'max_words' => (int) env('NEURONAI_STUDIO_RAG_CHUNK_MAX_WORDS', 200),
            'overlap_words' => (int) env('NEURONAI_STUDIO_RAG_CHUNK_OVERLAP_WORDS', 20),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments (Studio Chat)
    |--------------------------------------------------------------------------
    */

    'attachments' => [
        'disk' => env('NEURONAI_STUDIO_ATTACHMENTS_DISK', 'local'),
        'path' => env('NEURONAI_STUDIO_ATTACHMENTS_PATH', 'neuronai-studio/attachments'),
        'max_size_kb' => (int) env('NEURONAI_STUDIO_ATTACHMENTS_MAX_KB', 10240),
        'allowed_mimes' => [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'audio/mpeg',
            'audio/wav',
            'audio/ogg',
            'video/mp4',
            'video/webm',
            'application/pdf',
            'text/plain',
        ],
    ],

];
