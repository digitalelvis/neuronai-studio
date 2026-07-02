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
    | Inspector Integration
    |--------------------------------------------------------------------------
    */

    'inspector_enabled' => env('NEURONAI_STUDIO_INSPECTOR_ENABLED', false),

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

    'template_paths' => [
        'agent' => dirname(__DIR__).'/resources/templates/agents',
        'workflow' => dirname(__DIR__).'/resources/templates/workflows',
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
