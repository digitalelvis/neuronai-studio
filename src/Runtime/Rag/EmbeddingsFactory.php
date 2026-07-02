<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Rag;

use Closure;
use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use InvalidArgumentException;
use NeuronAI\RAG\Embeddings\CohereEmbeddingsProvider;
use NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface;
use NeuronAI\RAG\Embeddings\GeminiEmbeddingsProvider;
use NeuronAI\RAG\Embeddings\MistralEmbeddingsProvider;
use NeuronAI\RAG\Embeddings\OllamaEmbeddingsProvider;
use NeuronAI\RAG\Embeddings\OpenAIEmbeddingsProvider;
use NeuronAI\RAG\Embeddings\VoyageEmbeddingsProvider;

/**
 * Resolves an embeddings provider for a knowledge base. Built-in providers are
 * registered by default; developers can register or override any provider with
 * a custom resolver via {@see EmbeddingsFactory::extend()}.
 */
class EmbeddingsFactory
{
    /** @var array<string, Closure(array<string, mixed>): EmbeddingsProviderInterface> */
    protected array $resolvers = [];

    public function __construct()
    {
        $this->registerDefaults();
    }

    /**
     * Register (or override) a resolver for an embeddings provider.
     *
     * @param  Closure(array<string, mixed>): EmbeddingsProviderInterface  $resolver
     */
    public function extend(string $provider, Closure $resolver): void
    {
        $this->resolvers[$provider] = $resolver;
    }

    public function has(string $provider): bool
    {
        return isset($this->resolvers[$provider]);
    }

    public function make(KnowledgeBase $knowledgeBase): EmbeddingsProviderInterface
    {
        $provider = $knowledgeBase->embeddingsProvider();

        if (! isset($this->resolvers[$provider])) {
            throw new InvalidArgumentException(
                "No embeddings resolver registered for provider [{$provider}]."
            );
        }

        $config = (array) config("neuronai-studio.rag.embeddings.{$provider}", []);

        return ($this->resolvers[$provider])([
            'provider' => $provider,
            'model' => $knowledgeBase->embeddingsModel(),
            'config' => $config,
            'knowledge_base' => $knowledgeBase,
        ]);
    }

    protected function registerDefaults(): void
    {
        $this->resolvers['openai'] = function (array $ctx): EmbeddingsProviderInterface {
            $dimensions = $ctx['config']['dimensions'] ?? 1536;

            return new OpenAIEmbeddingsProvider(
                key: $this->resolveKey($ctx, 'OPENAI_API_KEY'),
                model: $ctx['model'],
                dimensions: $dimensions !== null ? (int) $dimensions : null,
            );
        };

        $this->resolvers['mistral'] = function (array $ctx): EmbeddingsProviderInterface {
            $dimensions = $ctx['config']['dimensions'] ?? null;

            return new MistralEmbeddingsProvider(
                key: $this->resolveKey($ctx, 'MISTRAL_API_KEY'),
                model: $ctx['model'],
                dimensions: $dimensions !== null ? (int) $dimensions : null,
            );
        };

        $this->resolvers['gemini'] = fn (array $ctx): EmbeddingsProviderInterface => new GeminiEmbeddingsProvider(
            key: $this->resolveKey($ctx, 'GEMINI_API_KEY'),
            model: $ctx['model'],
        );

        $this->resolvers['ollama'] = fn (array $ctx): EmbeddingsProviderInterface => new OllamaEmbeddingsProvider(
            model: $ctx['model'],
            url: (string) ($ctx['config']['url'] ?? 'http://localhost:11434/api'),
        );

        $this->resolvers['voyage'] = function (array $ctx): EmbeddingsProviderInterface {
            $dimensions = $ctx['config']['dimensions'] ?? null;

            return new VoyageEmbeddingsProvider(
                key: $this->resolveKey($ctx, 'VOYAGE_API_KEY'),
                model: $ctx['model'],
                dimensions: $dimensions !== null ? (int) $dimensions : null,
            );
        };

        $this->resolvers['cohere'] = fn (array $ctx): EmbeddingsProviderInterface => new CohereEmbeddingsProvider(
            key: $this->resolveKey($ctx, 'COHERE_API_KEY'),
            model: $ctx['model'],
        );
    }

    /**
     * Resolve the API key from the configured env var, falling back to the
     * matching neuron provider credentials.
     *
     * @param  array<string, mixed>  $ctx
     */
    protected function resolveKey(array $ctx, string $defaultEnv): string
    {
        $envKey = (string) ($ctx['config']['key_env'] ?? $defaultEnv);
        $key = env($envKey);

        if (empty($key)) {
            $key = config("neuron.provider.{$ctx['provider']}.key");
        }

        return (string) ($key ?? '');
    }
}
