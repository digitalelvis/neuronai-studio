<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Registry\ProviderRegistry;
use Generator;
use NeuronAI\Chat\Messages\Stream\Chunks\StreamChunk;
use NeuronAI\Chat\Messages\UserMessage;

class AgentRunner
{
    public function __construct(
        protected ProviderRegistry $providers,
        protected ToolResolver $toolResolver,
        protected McpToolResolver $mcpToolResolver,
        protected ToolEventExtractor $toolEvents,
        protected MessageFactory $messages,
    ) {}

    public function run(AgentDefinition $definition, string $message): AgentRunResult
    {
        $definition->loadMissing('mcpBindings');

        return $this->runInline([
            'provider' => $definition->provider,
            'model' => $definition->model,
            'instructions' => $definition->instructions,
            'tools' => $definition->tools ?? [],
        ], $message, $definition);
    }

    /** @param  array<string, mixed>  $payload */
    public function stream(AgentDefinition $definition, array $payload): Generator
    {
        $definition->loadMissing('mcpBindings');

        $agent = $this->makeAgent($definition, [
            'provider' => $definition->provider,
            'model' => $definition->model,
            'instructions' => $definition->instructions,
            'tools' => $definition->tools ?? [],
        ]);

        $message = $this->messages->userMessage(
            (string) ($payload['message'] ?? ''),
            is_array($payload['attachments'] ?? null) ? $payload['attachments'] : [],
        );

        $handler = $agent->stream($message);

        foreach ($handler->events() as $event) {
            if ($event instanceof StreamChunk) {
                yield $event;
            }
        }
    }

    public function runInline(array $config, string|UserMessage $message, ?AgentDefinition $definition = null): AgentRunResult
    {
        $agent = $this->makeAgent($definition, $config);
        $userMessage = $message instanceof UserMessage ? $message : new UserMessage($message);
        $handler = $agent->chat($userMessage);
        $content = $handler->getMessage()->getContent();
        $events = $this->toolEvents->fromChatHistory($agent->getChatHistory());

        return new AgentRunResult($content, $events);
    }

    /** @param  array<string, mixed>  $config */
    protected function makeAgent(?AgentDefinition $definition, array $config): DynamicAgent
    {
        $provider = $this->providers->resolve(
            $config['provider'] ?? config('neuronai-studio.default_provider'),
            $config['model'] ?? config('neuronai-studio.default_model'),
        );

        $tools = $this->toolResolver->resolveMany($config['tools'] ?? []);

        return new DynamicAgent(
            $provider,
            $definition,
            (string) ($config['instructions'] ?? 'You are a helpful AI assistant.'),
            $tools,
            $this->mcpToolResolver,
        );
    }
}
