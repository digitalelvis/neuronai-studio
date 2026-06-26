<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Registry\ProviderRegistry;
use ElvisLopesDigital\NeuronAIStudio\Support\ChatThreadKey;
use ElvisLopesDigital\NeuronAIStudio\Support\PlaygroundContext;
use ElvisLopesDigital\NeuronAIStudio\Support\ProviderParameters;
use Illuminate\Support\Str;
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

        $threadKey = $this->resolveThreadKey($definition, $payload);
        $config = $this->resolvePlaygroundConfig($definition, $payload);

        $agent = $this->makeAgent($definition, $config, $threadKey);

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

    public function runInline(array $config, string|UserMessage $message, ?AgentDefinition $definition = null, ?string $threadKey = null): AgentRunResult
    {
        $agent = $this->makeAgent($definition, $config, $threadKey);
        $userMessage = $message instanceof UserMessage ? $message : new UserMessage($message);
        $handler = $agent->chat($userMessage);
        $content = $handler->getMessage()->getContent();
        $events = $this->toolEvents->fromChatHistory($agent->getChatHistory());

        return new AgentRunResult($content, $events);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function resolvePlaygroundConfig(AgentDefinition $definition, array $payload): array
    {
        $instructions = isset($payload['instructions']) && is_string($payload['instructions']) && $payload['instructions'] !== ''
            ? $payload['instructions']
            : (string) $definition->instructions;

        $context = PlaygroundContext::normalize(
            is_array($payload['context'] ?? null) ? $payload['context'] : null,
        );

        $parameters = is_array($payload['parameters'] ?? null) ? $payload['parameters'] : [];

        return [
            'provider' => $definition->provider,
            'model' => $definition->model,
            'instructions' => PlaygroundContext::augmentInstructions($instructions, $context),
            'tools' => $definition->tools ?? [],
            'parameters' => $parameters,
        ];
    }

    /** @param  array<string, mixed>  $config */
    protected function makeAgent(?AgentDefinition $definition, array $config, ?string $threadKey = null): DynamicAgent
    {
        $provider = $this->providers->resolve(
            $config['provider'] ?? config('neuronai-studio.default_provider'),
            $config['model'] ?? config('neuronai-studio.default_model'),
            ProviderParameters::normalize(
                (string) ($config['provider'] ?? config('neuronai-studio.default_provider')),
                is_array($config['parameters'] ?? null) ? $config['parameters'] : [],
            ),
        );

        $tools = $this->toolResolver->resolveMany($config['tools'] ?? []);

        return new DynamicAgent(
            $provider,
            $definition,
            (string) ($config['instructions'] ?? 'You are a helpful AI assistant.'),
            $tools,
            $this->mcpToolResolver,
            $threadKey,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{key: ?string, public_id: ?string}
     */
    public function resolveThread(AgentDefinition $definition, array $payload): array
    {
        $publicId = isset($payload['thread_id']) && is_string($payload['thread_id']) && $payload['thread_id'] !== ''
            ? $payload['thread_id']
            : (string) Str::uuid();

        return [
            'key' => ChatThreadKey::forAgent($definition->id, $publicId),
            'public_id' => $publicId,
        ];
    }

    /** @param  array<string, mixed>  $payload */
    protected function resolveThreadKey(AgentDefinition $definition, array $payload): ?string
    {
        if (! array_key_exists('thread_id', $payload)) {
            return null;
        }

        return $this->resolveThread($definition, $payload)['key'];
    }
}
