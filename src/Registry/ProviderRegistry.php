<?php

namespace DigitalElvis\NeuronAIStudio\Registry;

use DigitalElvis\NeuronAIStudio\Support\ProviderParameters;
use InvalidArgumentException;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\Cohere\Cohere;
use NeuronAI\Providers\Deepseek\Deepseek;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Providers\HuggingFace\HuggingFace;
use NeuronAI\Providers\Mistral\Mistral;
use NeuronAI\Providers\Ollama\Ollama;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\OpenAI\Responses\OpenAIResponses;

class ProviderRegistry
{
    /** @return array<string, array{label: string, models: array}> */
    public function all(): array
    {
        return config('neuronai-studio.providers', []);
    }

    public function labels(): array
    {
        return collect($this->all())
            ->mapWithKeys(fn (array $config, string $key) => [$key => $config['label'] ?? $key])
            ->all();
    }

    public function modelsFor(string $provider): array
    {
        return config("neuronai-studio.providers.{$provider}.models", []);
    }

    /** @param  array<string, mixed>  $parameters */
    public function resolve(string $provider, ?string $model = null, array $parameters = []): AIProviderInterface
    {
        $config = config("neuron.provider.{$provider}");

        if (! is_array($config) || ! array_key_exists('model', $config)) {
            throw new InvalidArgumentException(
                "AI provider [{$provider}] is not configured. Publish config/neuron.php and set credentials in .env."
            );
        }

        if ($model !== null) {
            $config['model'] = $model;
        }

        if ($parameters !== []) {
            $base = is_array($config['parameters'] ?? null) ? $config['parameters'] : [];
            $config['parameters'] = ProviderParameters::merge($provider, $base, $parameters);
        }

        $this->assertProviderConfigured($provider, $config);

        return $this->makeProvider($provider, $config);
    }

    /** @param array<string, mixed> $config */
    protected function assertProviderConfigured(string $provider, array $config): void
    {
        if (! array_key_exists('key', $config)) {
            return;
        }

        $key = $config['key'];

        if ($key !== null && $key !== '') {
            return;
        }

        $envHint = match ($provider) {
            'openai', 'openai-responses', 'openai-tts', 'openai-stt' => 'OPENAI_KEY',
            'anthropic' => 'ANTHROPIC_KEY',
            'gemini' => 'GEMINI_KEY',
            'mistral' => 'MISTRAL_KEY',
            'deepseek' => 'DEEPSEEK_KEY',
            'huggingface' => 'HUGGINGFACE_KEY',
            'cohere' => 'COHERE_KEY',
            default => 'the provider key in config/neuron.php',
        };

        throw new InvalidArgumentException(
            "AI provider [{$provider}] is not configured. Set {$envHint} in your .env file, "
            .'or choose a different provider in the node settings.',
        );
    }

    /** @param array<string, mixed> $config */
    protected function makeProvider(string $provider, array $config): AIProviderInterface
    {
        return match ($provider) {
            'anthropic' => new Anthropic(...$config),
            'openai' => new OpenAI(...$config),
            'openai-responses' => new OpenAIResponses(...$config),
            'gemini' => new Gemini(...$config),
            'ollama' => new Ollama(...$config),
            'mistral' => new Mistral(...$config),
            'deepseek' => new Deepseek(...$config),
            'huggingface' => new HuggingFace(...$config),
            'cohere' => new Cohere(...$config),
            default => throw new InvalidArgumentException(
                "Unsupported AI provider [{$provider}]. Add support in ProviderRegistry or choose a configured provider."
            ),
        };
    }
}
