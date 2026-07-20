<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Events\RunUsageRecorded;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioTrace;
use DigitalElvis\NeuronAIStudio\Observability\ObservabilityManager;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Support\ChatThreadKey;
use DigitalElvis\NeuronAIStudio\Support\PlaygroundContext;
use DigitalElvis\NeuronAIStudio\Support\ProviderParameters;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\StructuredOutputValidationException;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\ToolApprovalRequiredException;
use DigitalElvis\NeuronAIStudio\Runtime\Memory\MemoryConfig;
use DigitalElvis\NeuronAIStudio\Usage\UsageRecorder;
use Illuminate\Support\Str;
use Generator;
use NeuronAI\Agent\AgentHandler;
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
            ...$this->toolControlConfigFromDefinition($definition),
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
            ...$this->toolControlConfigFromDefinition($definition),
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    public function stream(AgentDefinition $definition, array $payload): Generator
    {
        $definition->loadMissing('mcpBindings');

        $threadKey = $this->resolveThreadKey($definition, $payload);
        $config = $this->resolvePlaygroundConfig($definition, $payload);
        $messageText = (string) ($payload['message'] ?? '');

        [$run, $trace] = $this->createExecutionSession($definition, $threadKey, [
            'message' => $messageText,
        ]);

        $agent = $this->makeAgent($definition, $config, $threadKey);
        $agent->setPersistence(new InMemoryPersistence, $run->id);

        $this->attachObservability($agent, $run, $trace, $config);

        $message = $this->messages->userMessage(
            $messageText,
            is_array($payload['attachments'] ?? null) ? $payload['attachments'] : [],
        );

        $handler = $agent->stream($message);

        try {
            foreach ($handler->events() as $event) {
                if ($event instanceof StreamChunk) {
                    yield $event;
                }
            }

            $this->markRunCompleted($run, [
                'output' => ['content' => $handler->getMessage()->getContent()],
            ]);
        } catch (\Throwable $exception) {
            $this->markRunFailed($run, $exception);
            throw $exception;
        }
    }

    /**
     * Return the agent stream handler WITHOUT consuming its events, so an
     * external integration controller can drive it through a wire-protocol
     * adapter via `$handler->events($adapter)`. The internal playground path
     * (`stream()`) is left untouched (SA-08). Attaches a TelemetryTracker so
     * integrate streams meter usage when the consumer drains events.
     *
     * @param  array<string, mixed>  $payload
     */
    public function streamHandler(AgentDefinition $definition, array $payload): AgentHandler
    {
        $definition->loadMissing('mcpBindings');

        $threadKey = $this->resolveThreadKey($definition, $payload);
        $config = $this->resolvePlaygroundConfig($definition, $payload);
        $messageText = (string) ($payload['message'] ?? '');

        [$run, $trace] = $this->createExecutionSession($definition, $threadKey, [
            'message' => $messageText,
        ]);

        $agent = $this->makeAgent($definition, $config, $threadKey);
        $agent->setPersistence(new InMemoryPersistence, $run->id);

        $this->attachObservability($agent, $run, $trace, $config);

        $message = $this->messages->userMessage(
            $messageText,
            is_array($payload['attachments'] ?? null) ? $payload['attachments'] : [],
        );

        return $agent->stream($message);
    }

    protected function createExecutionSession(
        ?AgentDefinition $definition,
        ?string $threadKey = null,
        array $input = [],
        ?StudioRun $parentRun = null,
    ): array {
        $threadId = $threadKey;
        if ($threadId === null && $definition) {
            $threadId = (string) Str::uuid();
        }

        $thread = null;
        if ($threadId !== null) {
            if (str_contains($threadId, ':')) {
                $threadId = ChatThreadKey::publicId($threadId);
            }
            $thread = StudioThread::firstOrCreate([
                'id' => $threadId,
            ], [
                'entity_type' => AgentDefinition::class,
                'entity_id' => $definition ? $definition->id : null,
            ]);
        }

        $run = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread ? $thread->id : (string) Str::uuid(),
            'parent_run_id' => $parentRun?->id,
            'status' => 'running',
            'input' => $input,
            'started_at' => now(),
        ]);

        $trace = StudioTrace::create([
            'run_id' => $run->id,
        ]);

        return [$run, $trace];
    }

    public function runInline(
        array $config,
        string|UserMessage $message,
        ?AgentDefinition $definition = null,
        ?string $threadKey = null,
        bool $fake = false,
        ?StudioRun $parentRun = null,
    ): AgentRunResult {
        [$run, $trace] = $this->createExecutionSession($definition, $threadKey, [
            'message' => $message instanceof UserMessage ? $message->getContent() : $message
        ], $parentRun);

        $agent = $this->makeAgent($definition, $config, $run->thread_id, $fake);
        $agent->setPersistence(new InMemoryPersistence, $run->id);

        $this->attachObservability($agent, $run, $trace, $config, $parentRun);

        $userMessage = $message instanceof UserMessage ? $message : new UserMessage($message);

        try {
            $handler = $agent->chat($userMessage);
            $content = $handler->getMessage()->getContent();

            $this->markRunCompleted($run, [
                'output' => ['content' => $content],
            ]);

            $events = $this->toolEvents->fromChatHistory($agent->getChatHistory());
            return new AgentRunResult($content, $events, runId: $run->id);
        } catch (WorkflowInterrupt $interrupt) {
            $run->update([
                'status' => 'awaiting_tool_approval',
                'checkpoint_state' => [
                    'interrupt' => base64_encode(serialize($interrupt)),
                ],
                'finished_at' => null,
            ]);
            throw $this->toolApprovalException($interrupt, $run);
        } catch (\Throwable $exception) {
            $this->markRunFailed($run, $exception);
            throw $exception;
        }
    }

    /**
     * Stream an agent response inline, yielding chunks as they arrive and
     * returning the final AgentRunResult (content + tool events) once the
     * stream is fully consumed. Mirrors runInline for the token-streaming path.
     *
     * @param  array<string, mixed>  $config
     * @return Generator<int, StreamChunk, mixed, AgentRunResult>
     */
    public function streamInline(
        array $config,
        string|UserMessage $message,
        ?AgentDefinition $definition = null,
        ?string $threadKey = null,
        bool $fake = false,
        ?StudioRun $parentRun = null,
    ): Generator {
        [$run, $trace] = $this->createExecutionSession($definition, $threadKey, [
            'message' => $message instanceof UserMessage ? $message->getContent() : $message
        ], $parentRun);

        $agent = $this->makeAgent($definition, $config, $threadKey, $fake);
        $agent->setPersistence(new InMemoryPersistence, $run->id);

        $this->attachObservability($agent, $run, $trace, $config, $parentRun);

        $userMessage = $message instanceof UserMessage ? $message : new UserMessage($message);
        $handler = $agent->stream($userMessage);

        try {
            foreach ($handler->events() as $event) {
                if ($event instanceof StreamChunk) {
                    yield $event;
                }
            }

            $content = $handler->getMessage()->getContent();
            $this->markRunCompleted($run, [
                'output' => ['content' => $content],
            ]);

            $events = $this->toolEvents->fromChatHistory($agent->getChatHistory());
            return new AgentRunResult($content, $events, runId: $run->id);
        } catch (WorkflowInterrupt $interrupt) {
            $run->update([
                'status' => 'awaiting_tool_approval',
                'checkpoint_state' => [
                    'interrupt' => base64_encode(serialize($interrupt)),
                ],
                'finished_at' => null,
            ]);
            throw $this->toolApprovalException($interrupt, $run);
        } catch (\Throwable $exception) {
            $this->markRunFailed($run, $exception);
            throw $exception;
        }
    }

    /**
     * Translate a NeuronAI ToolApproval interrupt into a Studio-level exception
     * carrying the tools awaiting human approval. Non-approval interrupts bubble up.
     */
    protected function toolApprovalException(WorkflowInterrupt $interrupt, ?StudioRun $run = null): WorkflowInterrupt|ToolApprovalRequiredException
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

        return new ToolApprovalRequiredException(
            '',
            $pendingTools,
            $request->getMessage(),
            $run ? base64_encode(serialize($interrupt)) : serialize($interrupt)
        );
    }

    /**
     * Resume an agent that paused for tool approval by restoring the persisted
     * NeuronAI interrupt, applying the human decision, and re-running the node.
     *
     * @param  array<string, mixed>  $config
     */
    public function resumeInlineApproval(
        array $config,
        string|StudioRun $run,
        string $decision,
        ?string $feedback = null,
        ?AgentDefinition $definition = null,
        ?string $threadKey = null,
        ?StudioRun $parentRun = null,
    ): AgentRunResult {
        if (is_string($run)) {
            if (Str::isUuid($run) || strlen($run) < 100) {
                $run = StudioRun::findOrFail($run);
            } else {
                return $this->resumeInlineApprovalLegacy($config, $run, $decision, $feedback, $definition, $threadKey);
            }
        }

        $serializedInterrupt = base64_decode($run->checkpoint_state['interrupt']);
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

        $run->update([
            'status' => 'running',
            'finished_at' => null,
        ]);

        $trace = $run->traces()->latest()->first() ?? StudioTrace::create(['run_id' => $run->id]);

        $agent = $this->makeAgent($definition, $config, $threadKey);
        $agent->setPersistence(new InMemoryPersistence, $run->id);

        $this->attachObservability(
            $agent,
            $run,
            $trace,
            $config,
            $parentRun ?? $this->resolveParentRun($run),
        );

        $persistence = new InMemoryPersistence;
        $resumeToken = 'studio_tool_approval';
        $persistence->save($resumeToken, $interrupt);
        $agent->setPersistence($persistence, $resumeToken);

        try {
            $handler = $agent->chat([], $request);
            $content = $handler->getMessage()->getContent();

            $this->markRunCompleted($run, [
                'output' => ['content' => $content],
            ]);

            $events = $this->toolEvents->fromChatHistory($agent->getChatHistory());
            return new AgentRunResult($content, $events, runId: $run->id);
        } catch (WorkflowInterrupt $reinterrupt) {
            $run->update([
                'status' => 'awaiting_tool_approval',
                'checkpoint_state' => [
                    'interrupt' => base64_encode(serialize($reinterrupt)),
                ],
                'finished_at' => null,
            ]);
            throw $this->toolApprovalException($reinterrupt, $run);
        } catch (\Throwable $exception) {
            $this->markRunFailed($run, $exception);
            throw $exception;
        }
    }

    protected function resumeInlineApprovalLegacy(
        array $config,
        string $serializedInterrupt,
        string $decision,
        ?string $feedback = null,
        ?AgentDefinition $definition = null,
        ?string $threadKey = null,
    ): AgentRunResult {
        $agent = $this->makeAgent($definition, $config, $threadKey);

        if (! str_starts_with($serializedInterrupt, 'O:')) {
            $decoded = base64_decode($serializedInterrupt, true);
            if (is_string($decoded)) {
                $serializedInterrupt = $decoded;
            }
        }

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
        ?StudioRun $parentRun = null,
    ): AgentRunResult {
        [$run, $trace] = $this->createExecutionSession($definition, $threadKey, [
            'message' => $message instanceof UserMessage ? $message->getContent() : $message
        ], $parentRun);

        $agent = $this->makeAgent($definition, $config, $threadKey, $fake);
        $agent->setPersistence(new InMemoryPersistence, $run->id);

        $this->attachObservability($agent, $run, $trace, $config, $parentRun);

        $userMessage = $message instanceof UserMessage ? $message : new UserMessage($message);

        try {
            $result = $agent->structured($userMessage, $outputClass);

            $this->markRunCompleted($run, [
                'output' => ['structured' => $this->normalizeStructuredOutput($result)],
            ]);

            $events = $this->toolEvents->fromChatHistory($agent->getChatHistory());
            return new AgentRunResult(
                toolEvents: $events,
                structured: $this->normalizeStructuredOutput($result),
                runId: $run->id,
            );
        } catch (AgentException $exception) {
            $this->markRunFailed($run, $exception);
            throw StructuredOutputValidationException::fromAgentException($exception);
        } catch (ProviderException $exception) {
            $this->markRunFailed($run, $exception);
            throw new StructuredOutputValidationException(
                $exception->getMessage(),
                [$exception->getMessage()],
                $exception,
            );
        } catch (\Throwable $exception) {
            $this->markRunFailed($run, $exception);
            throw $exception;
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

    /** @param  array<string, mixed>  $config */
    protected function attachObservability(
        object $target,
        StudioRun $run,
        StudioTrace $trace,
        array $config,
        ?StudioRun $parentRun = null,
        bool $trackNodes = true,
    ): void {
        $provider = isset($config['provider']) && is_string($config['provider']) && $config['provider'] !== ''
            ? $config['provider']
            : null;
        $model = isset($config['model']) && is_string($config['model']) && $config['model'] !== ''
            ? $config['model']
            : null;

        app(ObservabilityManager::class)->attach($target, [
            'run' => $run,
            'trace' => $trace,
            'track_nodes' => $trackNodes,
            'provider' => $provider,
            'model' => $model,
            'parent_run' => $parentRun,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    protected function markRunCompleted(StudioRun $run, array $attributes = []): void
    {
        $run->update(array_merge([
            'status' => 'completed',
            'finished_at' => now(),
        ], $attributes));

        $this->finalizeRunUsage($run);
    }

    protected function markRunFailed(StudioRun $run, \Throwable $exception): void
    {
        $run->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'finished_at' => now(),
        ]);

        $this->finalizeRunUsage($run);
    }

    protected function finalizeRunUsage(StudioRun $run): void
    {
        (new UsageRecorder)->finalizeRun($run);
        $this->dispatchRunUsageRecorded($run->fresh() ?? $run);
    }

    protected function dispatchRunUsageRecorded(StudioRun $run): void
    {
        if (! config('neuronai-studio.usage.events.enabled', false)) {
            return;
        }

        event(RunUsageRecorded::fromRun($run));
    }

    protected function resolveParentRun(StudioRun $run): ?StudioRun
    {
        if ($run->relationLoaded('parent')) {
            return $run->parent;
        }

        if ($run->parent_run_id === null) {
            return null;
        }

        return StudioRun::query()->find($run->parent_run_id);
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
        $memory = $this->resolveMemoryConfig($definition, $config);

        $agent = new DynamicAgent(
            $provider,
            $definition,
            (string) ($config['instructions'] ?? 'You are a helpful AI assistant.'),
            $tools,
            $this->mcpToolResolver,
            $threadKey,
            $memory->contextWindow(),
            $memory,
        );

        if (($config['require_tool_approval'] ?? false) === true) {
            $agent->addGlobalMiddleware(new ToolApproval);
        }

        $this->applyToolControls($agent, $config, $definition);

        return $agent;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function resolveMemoryConfig(?AgentDefinition $definition, array $config = []): MemoryConfig
    {
        $base = MemoryConfig::fromArray(
            is_array($definition?->memory_config) ? $definition->memory_config : null,
        );

        return $base->merge(MemoryConfig::fromArray($this->memoryOverrideFromConfig($config)));
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     */
    protected function memoryOverrideFromConfig(array $config): ?array
    {
        if (isset($config['memory_config']) && is_array($config['memory_config'])) {
            return $config['memory_config'];
        }

        $keys = [
            'context_window',
            'driver',
            'summarization_enabled',
            'summarization_threshold',
            'budget_rag',
            'budget_tool_results',
            'budget_state',
        ];

        $flat = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $config) && $config[$key] !== null && $config[$key] !== '') {
                $flat[$key] = $config[$key];
            }
        }

        return $flat === [] ? null : $flat;
    }

    /**
     * @return array{tool_max_runs?: int, parallel_tool_calls?: bool}
     */
    public function toolControlConfigFromDefinition(AgentDefinition $definition): array
    {
        $config = [];

        if ($definition->tool_max_runs !== null) {
            $config['tool_max_runs'] = (int) $definition->tool_max_runs;
        }

        if ($definition->parallel_tool_calls !== null) {
            $config['parallel_tool_calls'] = (bool) $definition->parallel_tool_calls;
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function applyToolControls(DynamicAgent $agent, array $config, ?AgentDefinition $definition): void
    {
        $maxRuns = array_key_exists('tool_max_runs', $config)
            ? $config['tool_max_runs']
            : $definition?->tool_max_runs;

        if ($maxRuns !== null && (int) $maxRuns >= 1) {
            $agent->toolMaxRuns((int) $maxRuns);
        }

        $parallel = array_key_exists('parallel_tool_calls', $config)
            ? $config['parallel_tool_calls']
            : $definition?->parallel_tool_calls;

        if ($parallel !== null) {
            $agent->parallelToolCalls((bool) $parallel);
        }
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
