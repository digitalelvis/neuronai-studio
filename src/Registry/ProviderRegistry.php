<?php

namespace ElvisLopesDigital\NeuronAIStudio\Registry;

use NeuronAI\Laravel\Facades\AIProvider;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
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

    public function resolve(string $provider, ?string $model = null): AIProviderInterface
    {
        if ($model === null) {
            return AIProvider::driver($provider);
        }

        $config = config("neuron.provider.{$provider}");

        if (! is_array($config) || ! array_key_exists('model', $config)) {
            return AIProvider::driver($provider);
        }

        $config['model'] = $model;

        return $this->makeProvider($provider, $config);
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
            default => AIProvider::driver($provider),
        };
    }
}
