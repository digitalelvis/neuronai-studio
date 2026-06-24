<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    */

    'route_prefix' => env('NEURONAI_STUDIO_ROUTE_PREFIX', 'neuronai-studio'),

    'middleware' => ['web', 'neuronai-studio.auth'],

    'gate' => 'viewNeuronAIStudio',

    /*
    |--------------------------------------------------------------------------
    | Export Configuration
    |--------------------------------------------------------------------------
    */

    'export_namespace' => env('NEURONAI_STUDIO_EXPORT_NAMESPACE', 'App\\Neuron'),

    'export_path' => env('NEURONAI_STUDIO_EXPORT_PATH', app_path('Neuron')),

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
    | Queue Configuration
    |--------------------------------------------------------------------------
    */

    'queue' => env('NEURONAI_STUDIO_QUEUE', 'default'),

    'queue_connection' => env('NEURONAI_STUDIO_QUEUE_CONNECTION'),

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
