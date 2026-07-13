<?php

namespace DigitalElvis\NeuronAIStudio\Integration;

use InvalidArgumentException;
use NeuronAI\Chat\Messages\Stream\Adapters\AGUIAdapter;
use NeuronAI\Chat\Messages\Stream\Adapters\StreamAdapterInterface;
use NeuronAI\Chat\Messages\Stream\Adapters\VercelAIAdapter;

/**
 * Catalog + factory for external stream protocol adapters.
 *
 * `available()` lists protocols wired to a real neuron-ai adapter and enabled
 * via config; `roadmap()` lists catalog-only protocols (metadata, no adapter).
 * `resolve()` instantiates the neuron-ai adapter for an enabled protocol.
 */
class StreamAdapterRegistry
{
    /**
     * Protocols backed by a concrete neuron-ai adapter.
     *
     * @var array<string, array{label: string, framework: string, adapter: class-string<StreamAdapterInterface>, headers: array<string, string>, docs: string}>
     */
    protected array $adapters = [
        'vercel' => [
            'label' => 'Vercel AI SDK',
            'framework' => 'Vercel AI SDK (useChat, ai/react)',
            'adapter' => VercelAIAdapter::class,
            'headers' => ['x-vercel-ai-ui-message-stream' => 'v1'],
            'docs' => 'https://ai-sdk.dev/docs/ai-sdk-ui/stream-protocol',
        ],
        'agui' => [
            'label' => 'AG-UI',
            'framework' => 'AG-UI clients',
            'adapter' => AGUIAdapter::class,
            'headers' => ['Content-Type' => 'text/event-stream'],
            'docs' => 'https://docs.ag-ui.com/concepts/events',
        ],
    ];

    /**
     * Catalog-only protocols on the roadmap (no adapter shipped in this feature).
     *
     * @var array<string, array{label: string, framework: string, notes: string}>
     */
    protected array $roadmap = [
        'openai-sse' => [
            'label' => 'OpenAI SSE',
            'framework' => 'OpenAI-compatible clients',
            'notes' => 'Chat Completions SSE',
        ],
        'anthropic-sse' => [
            'label' => 'Anthropic SSE',
            'framework' => 'Anthropic Messages API',
            'notes' => 'Messages SSE',
        ],
        'langchain' => [
            'label' => 'LangChain',
            'framework' => 'LangChain / LangServe',
            'notes' => '',
        ],
        'copilotkit' => [
            'label' => 'CopilotKit',
            'framework' => 'CopilotKit',
            'notes' => '',
        ],
        'websocket' => [
            'label' => 'WebSocket',
            'framework' => 'Laravel Reverb / Echo',
            'notes' => 'Realtime broadcast',
        ],
        'inertia' => [
            'label' => 'Inertia.js',
            'framework' => 'Inertia.js streaming',
            'notes' => '',
        ],
        'ndjson' => [
            'label' => 'NDJSON',
            'framework' => 'Generic clients',
            'notes' => 'NDJSON / plain JSON stream',
        ],
    ];

    /**
     * Protocols available to consume: shipped adapter + enabled in config.
     *
     * @return array<string, array<string, mixed>>
     */
    public function available(): array
    {
        $available = [];

        foreach ($this->adapters as $protocol => $meta) {
            if (! $this->isEnabled($protocol)) {
                continue;
            }

            $available[$protocol] = [
                'protocol' => $protocol,
                'label' => $meta['label'],
                'framework' => $meta['framework'],
                'headers' => $meta['headers'],
                'docs' => $meta['docs'],
                'status' => 'available',
            ];
        }

        return $available;
    }

    /**
     * Catalog-only protocols planned for future delivery.
     *
     * @return array<string, array<string, mixed>>
     */
    public function roadmap(): array
    {
        $roadmap = [];

        foreach ($this->roadmap as $protocol => $meta) {
            $roadmap[$protocol] = [
                'protocol' => $protocol,
                'label' => $meta['label'],
                'framework' => $meta['framework'],
                'notes' => $meta['notes'],
                'status' => 'roadmap',
            ];
        }

        return $roadmap;
    }

    /**
     * Resolve a protocol name into a concrete neuron-ai adapter instance.
     *
     * @throws InvalidArgumentException when the protocol is unknown or disabled
     */
    public function resolve(string $protocol, ?string $threadId = null): StreamAdapterInterface
    {
        if (! isset($this->adapters[$protocol])) {
            throw new InvalidArgumentException("Unknown stream protocol [{$protocol}].");
        }

        if (! $this->isEnabled($protocol)) {
            throw new InvalidArgumentException("Stream protocol [{$protocol}] is disabled.");
        }

        return match ($protocol) {
            'agui' => new AGUIAdapter($threadId),
            default => new VercelAIAdapter,
        };
    }

    public function isEnabled(string $protocol): bool
    {
        if (! isset($this->adapters[$protocol])) {
            return false;
        }

        return (bool) config("neuronai-studio.stream_adapters.protocols.{$protocol}.enabled", false);
    }
}
