<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Registry\ProviderRegistry;

class AgentRunner
{
    public function __construct(
        protected ProviderRegistry $providers,
    ) {}

    public function run(AgentDefinition $definition, string $message): string
    {
        return $this->runInline([
            'provider' => $definition->provider,
            'model' => $definition->model,
            'instructions' => $definition->instructions,
        ], $message);
    }

    public function runInline(array $config, string $message): string
    {
        $provider = $this->providers->resolve(
            $config['provider'] ?? config('neuronai-studio.default_provider'),
            $config['model'] ?? config('neuronai-studio.default_model'),
        );

        $agent = new DynamicAgent(
            $provider,
            (string) ($config['instructions'] ?? 'You are a helpful AI assistant.'),
        );

        return $agent->chat(new UserMessage($message))->getMessage()->getContent();
    }
}
