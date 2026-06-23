<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Registry\ProviderRegistry;
use NeuronAI\Chat\Messages\UserMessage;

class AgentRunner
{
    public function __construct(
        protected ProviderRegistry $providers,
        protected ToolResolver $toolResolver,
        protected ToolEventExtractor $toolEvents,
    ) {}

    public function run(AgentDefinition $definition, string $message): AgentRunResult
    {
        return $this->runInline([
            'provider' => $definition->provider,
            'model' => $definition->model,
            'instructions' => $definition->instructions,
            'tools' => $definition->tools ?? [],
        ], $message);
    }

    public function runInline(array $config, string $message): AgentRunResult
    {
        $provider = $this->providers->resolve(
            $config['provider'] ?? config('neuronai-studio.default_provider'),
            $config['model'] ?? config('neuronai-studio.default_model'),
        );

        $tools = $this->toolResolver->resolveMany($config['tools'] ?? []);

        $agent = new DynamicAgent(
            $provider,
            (string) ($config['instructions'] ?? 'You are a helpful AI assistant.'),
            $tools,
        );

        $handler = $agent->chat(new UserMessage($message));
        $content = $handler->getMessage()->getContent();
        $events = $this->toolEvents->fromChatHistory($agent->getChatHistory());

        return new AgentRunResult($content, $events);
    }
}
