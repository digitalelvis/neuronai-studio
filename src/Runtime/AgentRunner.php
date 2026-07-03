<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Support\ChatThreadKey;
use DigitalElvis\NeuronAIStudio\Support\PlaygroundContext;
use DigitalElvis\NeuronAIStudio\Support\ProviderParameters;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\StructuredOutputValidationException;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\ToolApprovalRequiredException;
use Illuminate\Support\Str;
use Generator;
use NeuronAI\Agent\Events\ToolCallEvent;
use NeuronAI\Agent\Middleware\ToolApproval;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Stream\Chunks\StreamChunk;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Exceptions\AgentException;
use NeuronAI\Exceptions\ProviderException;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Workflow\Interrupt\ApprovalRequest;
use NeuronAI\Workflow\Interrupt\WorkflowInterrupt;
use NeuronAI\Workflow\Persistence\InMemoryPersistence;

class AgentRunner
{
    public function __construct(
        protected ProviderRegistry $providers,
        protected ToolResolver $toolResolver,
        protected McpToolResolver $mcpToolResolver,
        protected ToolEventExtractor $toolEvents,
        protected MessageFactory $messages,
    ) {}

    public function run(AgentDefinition $definition, string $message, bool $fake = false): AgentRunResult
    {
        $definition->loadMissing('mcpBindings');

        return $this->runInline([
            'provider' => $definition->provider,
            'model' => $definition->model,
            'instructions' => $definition->instructions,
            'tools' => $definition->tools ?? [],
            'require_tool_approval' => (bool) $definition->require_tool_approval,
        ], $message, $definition, fake: $fake);
    }

    public function resolveAgent(AgentDefinition $definition): DynamicAgent
    {
        $definition->loadMissing('mcpBindings');

        return $this->makeAgent($definition, [
            'provider' => $definition->provider,
            'model' => $definition->model,
            'instructions' => $definition->instructions,
            'tools' => $definition->tools ?? [],
            'require_tool_approval' => (bool) $definition->require_tool_approval,
        ]);
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

    public function runInline(array $config, string|UserMessage $message, ?AgentDefinition $definition = null, ?string $threadKey = null, bool $fake = false): AgentRunResult
    {
        $agent = $this->makeAgent($definition, $config, $threadKey, $fake);
        $userMessage = $message instanceof UserMessage ? $message : new UserMessage($message);
        $handler = $agent->chat($userMessage);

        try {
            $content = $handler->getMessage()->getContent();
        } catch (WorkflowInterrupt $interrupt) {
            throw $this->toolApprovalException($interrupt);
        }

        $events = $this->toolEvents->fromChatHistory($agent->getChatHistory());

        return new AgentRunResult($content, $events);
    }

    /**
     * Translate a NeuronAI ToolApproval interrupt into a Studio-level exception
     * carrying the tools awaiting human approval. Non-approval interrupts bubble up.
     */
    protected function toolApprovalException(WorkflowInterrupt $interrupt): WorkflowInterrupt|ToolApprovalRequiredException
    {
        $request = $interrupt->getRequest();

        if (! $request instanceof ApprovalRequest) {
            return $interrupt;
        }

        $approvedIds = array_map(static fn ($action) => $action->id, $request->getActions());
        $event = $interrupt->getEvent();
        $pendingTools = [];

        if ($event instanceof ToolCallEvent) {
            foreach ($event->toolCallMessage->getTools() as $tool) {
                if (! in_array($tool->getCallId(), $approvedIds, true)) {
                    continue;
                }

                $pendingTools[] = [
                    'name' => $tool->getName(),
                    'arguments' => $tool->getInputs(),
                    'call_id' => $tool->getCallId(),
                ];
            }
        }

        return new ToolApprovalRequiredException('', $pendingTools, $request->getMessage(), serialize($interrupt));
    }

    /**
     * Resume an agent that paused for tool approval by restoring the persisted
     * NeuronAI interrupt, applying the human decision, and re-running the node.
     *
     * @param  array<string, mixed>  $config
     */
    public function resumeInlineApproval(
        array $config,
        string $serializedInterrupt,
        string $decision,
        ?string $feedback = null,
        ?AgentDefinition $definition = null,
        ?string $threadKey = null,
    ): AgentRunResult {
        $agent = $this->makeAgent($definition, $config, $threadKey);

        /** @var WorkflowInterrupt $interrupt */
        $interrupt = unserialize($serializedInterrupt);
        $request = $interrupt->getRequest();

        if ($request instanceof ApprovalRequest) {
            foreach ($request->getActions() as $action) {
                $decision === 'reject'
                    ? $action->reject($feedback)
                    : $action->approve($feedback);
            }
        }

        $persistence = new InMemoryPersistence;
        $resumeToken = 'studio_tool_approval';
        $persistence->save($resumeToken, $interrupt);
        $agent->setPersistence($persistence, $resumeToken);

        $handler = $agent->chat([], $request);

        try {
            $content = $handler->getMessage()->getContent();
        } catch (WorkflowInterrupt $reinterrupt) {
            throw $this->toolApprovalException($reinterrupt);
        }

        $events = $this->toolEvents->fromChatHistory($agent->getChatHistory());

        return new AgentRunResult($content, $events);
    }

    public function structuredInline(
        array $config,
        string|UserMessage $message,
        string $outputClass,
        ?AgentDefinition $definition = null,
        ?string $threadKey = null,
        bool $fake = false,
    ): AgentRunResult {
        try {
            $agent = $this->makeAgent($definition, $config, $threadKey, $fake);
            $userMessage = $message instanceof UserMessage ? $message : new UserMessage($message);
            $result = $agent->structured($userMessage, $outputClass);
            $events = $this->toolEvents->fromChatHistory($agent->getChatHistory());

            return new AgentRunResult(
                toolEvents: $events,
                structured: $this->normalizeStructuredOutput($result),
            );
        } catch (AgentException $exception) {
            throw StructuredOutputValidationException::fromAgentException($exception);
        } catch (ProviderException $exception) {
            throw new StructuredOutputValidationException(
                $exception->getMessage(),
                [$exception->getMessage()],
                $exception,
            );
        }
    }

    protected function normalizeStructuredOutput(mixed $result): array
    {
        if (is_array($result)) {
            return $result;
        }

        if (is_object($result)) {
            return json_decode(json_encode($result, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        }

        return ['value' => $result];
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
    protected function makeAgent(?AgentDefinition $definition, array $config, ?string $threadKey = null, bool $fake = false): DynamicAgent
    {
        if ($fake) {
            $provider = new FakeAIProvider(new AssistantMessage('Eval fake response'));
        } else {
            $provider = $this->providers->resolve(
                $config['provider'] ?? config('neuronai-studio.default_provider'),
                $config['model'] ?? config('neuronai-studio.default_model'),
                ProviderParameters::normalize(
                    (string) ($config['provider'] ?? config('neuronai-studio.default_provider')),
                    is_array($config['parameters'] ?? null) ? $config['parameters'] : [],
                ),
            );
        }

        $tools = $this->toolResolver->resolveMany($config['tools'] ?? []);

        $agent = new DynamicAgent(
            $provider,
            $definition,
            (string) ($config['instructions'] ?? 'You are a helpful AI assistant.'),
            $tools,
            $this->mcpToolResolver,
            $threadKey,
        );

        if (($config['require_tool_approval'] ?? false) === true) {
            $agent->addGlobalMiddleware(new ToolApproval);
        }

        return $agent;
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
