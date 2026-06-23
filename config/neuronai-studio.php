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
        // 'example' => [
        //     'label' => 'Example MCP',
        //     'description' => 'Example MCP server',
        //     'connector' => [
        //         'command' => 'npx',
        //         'args' => ['-y', '@modelcontextprotocol/server-example'],
        //     ],
        // ],
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
    ],

];
