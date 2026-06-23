<?php

namespace ElvisLopesDigital\NeuronAIStudio\Registry;

use NeuronAI\Laravel\Facades\AIProvider;
use NeuronAI\Providers\AIProviderInterface;

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
        return AIProvider::driver($provider);
    }
}
