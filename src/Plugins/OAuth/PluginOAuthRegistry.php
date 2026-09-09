<?php

namespace DigitalElvis\NeuronAIStudio\Plugins\OAuth;

class PluginOAuthRegistry
{
    /** @return array<string, array<string, mixed>> */
    public function providers(): array
    {
        return (array) config('neuronai-studio.plugins.oauth.providers', []);
    }

    /** @return array<string, mixed>|null */
    public function forSlug(string $slug): ?array
    {
        $provider = $this->providers()[$slug] ?? null;

        if (! is_array($provider)) {
            return null;
        }

        return $provider;
    }

    public function isConfigured(string $slug): bool
    {
        $provider = $this->forSlug($slug);

        if ($provider === null) {
            return false;
        }

        $clientId = (string) ($provider['client_id'] ?? '');

        return $clientId !== '';
    }
}
