<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\ToolApprovalRequiredException;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;
use DigitalElvis\NeuronAIStudio\Runtime\StructuredOutput\StructuredOutputResolver;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolCallChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolResultChunk;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Workflow\WorkflowState;

class AgentNodeExecutor implements NodeExecutorInterface
{
    public function __construct(
        protected AgentRunner $agentRunner,
        protected MessageFactory $messages,
        protected StructuredOutputResolver $outputResolver,
    ) {}

    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $nodeId = (string) ($nodeConfig['id'] ?? 'agent');
        $data = $nodeConfig['data'] ?? [];
        $outputKey = $data['output_key'] ?? 'agent_response';
        $rawMessage = array_key_exists('message', $data)
            ? (string) $data['message']
            : (string) $state->get('input', '');

        if ($rawMessage === '') {
            $rawMessage = (string) $state->get('input', '');
        }

        $definition = isset($data['agent_id']) ? AgentDefinition::findOrFail($data['agent_id']) : null;
        $memory = $this->agentRunner->resolveMemoryConfig($definition, $this->memoryOverrideConfig($data, $definition));
        $truncationEvents = [];
        $message = StateTemplateInterpolator::interpolate($rawMessage, $state, $memory, $truncationEvents);
        if ($truncationEvents !== []) {
            $state->set('__studio_context_truncations', $truncationEvents);
        }
        $attachments = is_array($state->get('attachments')) ? $state->get('attachments') : [];
        $userMessage = $this->messages->resolveMessageWithAttachments($message, $attachments);
        $threadKey = $state->get('__studio_thread_id');
        $threadKey = is_string($threadKey) && $threadKey !== '' ? $threadKey : null;

        $parentRun = $this->resolveParentRun($state);

        $resume = $state->get('__tool_approval_resume');
        if (is_array($resume) && ($resume['node_id'] ?? null) === $nodeId) {
            $state->set('__tool_approval_resume', null);

            return $this->resumeApproval($nodeId, (string) $outputKey, $data, $definition, $threadKey, $resume, $state, $context, $parentRun);
        }

        $requireApproval = array_key_exists('require_tool_approval', $data)
            ? (bool) $data['require_tool_approval']
            : (bool) ($definition?->require_tool_approval ?? false);

        if ($data['structured'] ?? false) {
            $outputClass = $this->outputResolver->resolve((string) ($data['output_class'] ?? ''));
            $config = $definition !== null
                ? [
                    'provider' => $definition->provider,
                    'model' => $definition->model,
                    'instructions' => $definition->instructions,
                    'tools' => $definition->tools ?? [],
                    ...$this->toolControlConfig($data, $definition),
                    ...$this->memoryOverrideConfig($data, $definition),
                ]
                : array_merge($data, $this->toolControlConfig($data, null), $this->memoryOverrideConfig($data, null));

            $response = $this->agentRunner->structuredInline(
                $config,
                $userMessage,
                $outputClass,
                $definition,
                $threadKey,
                parentRun: $parentRun,
            );
            $state->set($outputKey, $response->structured);
            $this->captureRunUsage($state, $response->runId);
            $this->flushContextTruncations($state, $response->runId);

            return 'default';
        }

        if ($this->shouldStream($data, $requireApproval, $state)) {
            return $this->streamResponse($nodeId, (string) $outputKey, $data, $definition, $userMessage, $threadKey, $state, $parentRun);
        }

        try {
            if ($definition !== null) {
                $response = $this->agentRunner->runInline([
                    'provider' => $definition->provider,
                    'model' => $definition->model,
                    'instructions' => $definition->instructions,
                    'tools' => $definition->tools ?? [],
                    'require_tool_approval' => $requireApproval,
                    ...$this->toolControlConfig($data, $definition),
                    ...$this->memoryOverrideConfig($data, $definition),
                ], $userMessage, $definition, $threadKey, parentRun: $parentRun);
            } else {
                $response = $this->agentRunner->runInline(
                    array_merge($data, [
                        'require_tool_approval' => $requireApproval,
                        ...$this->toolControlConfig($data, null),
                        ...$this->memoryOverrideConfig($data, null),
                    ]),
                    $userMessage,
                    null,
                    $threadKey,
                    parentRun: $parentRun,
                );
            }
        } catch (ToolApprovalRequiredException $exception) {
            throw new ToolApprovalRequiredException($nodeId, $exception->pendingTools, $exception->approvalMessage, $exception->serializedInterrupt);
        }

        $state->set($outputKey, $response->content);
        $this->emitToolEvents($nodeId, $response->toolEvents, $state);
        $this->captureRunUsage($state, $response->runId);
        $this->flushContextTruncations($state, $response->runId);

        return 'default';
    }

    /**
     * Resume a paused agent node after a human tool-approval decision.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $resume
     */
    protected function resumeApproval(
        string $nodeId,
        string $outputKey,
        array $data,
        ?AgentDefinition $definition,
        ?string $threadKey,
        array $resume,
        WorkflowState $state,
        GraphContext $context,
        ?StudioRun $parentRun = null,
    ): string {
        $decision = (string) ($resume['decision'] ?? 'approve');
        $serialized = (string) ($resume['interrupt'] ?? '');
        $feedback = is_string($resume['feedback'] ?? null) && $resume['feedback'] !== '' ? $resume['feedback'] : null;

        $config = $definition !== null
            ? [
                'provider' => $definition->provider,
                'model' => $definition->model,
                'instructions' => $definition->instructions,
                'tools' => $definition->tools ?? [],
                'require_tool_approval' => true,
                ...$this->toolControlConfig($data, $definition),
                ...$this->memoryOverrideConfig($data, $definition),
            ]
            : array_merge($data, [
                'require_tool_approval' => true,
                ...$this->toolControlConfig($data, null),
                ...$this->memoryOverrideConfig($data, null),
            ]);

        try {
            $response = $this->agentRunner->resumeInlineApproval(
                $config,
                $serialized,
                $decision,
                $feedback,
                $definition,
                $threadKey,
                $parentRun,
            );
        } catch (ToolApprovalRequiredException $exception) {
            throw new ToolApprovalRequiredException($nodeId, $exception->pendingTools, $exception->approvalMessage, $exception->serializedInterrupt);
        }

        $state->set($outputKey, $response->content);
        $this->emitToolEvents($nodeId, $response->toolEvents, $state);
        $this->captureRunUsage($state, $response->runId);

        if ($decision === 'reject' && $context->targetForHandle($nodeId, 'rejected') !== null) {
            return 'rejected';
        }

        return 'default';
    }

    /**
     * Token streaming only applies to interactive SSE runs (state carries an
     * emitter) and never to structured output or tool-approval nodes, which
     * rely on the blocking path for validation and interrupt handling.
     *
     * @param  array<string, mixed>  $data
     */
    protected function shouldStream(array $data, bool $requireApproval, WorkflowState $state): bool
    {
        return ($data['stream'] ?? false) === true
            && $requireApproval === false
            && $state instanceof BuilderWorkflowState
            && $state->stepEmitter !== null;
    }

    /**
     * Consume the agent stream, emitting a `token` event per text chunk while
     * accumulating the final content and tool events for the workflow state.
     *
     * @param  array<string, mixed>  $data
     */
    protected function streamResponse(
        string $nodeId,
        string $outputKey,
        array $data,
        ?AgentDefinition $definition,
        UserMessage $userMessage,
        ?string $threadKey,
        BuilderWorkflowState $state,
        ?StudioRun $parentRun = null,
    ): string {
        $config = $definition !== null
            ? [
                'provider' => $definition->provider,
                'model' => $definition->model,
                'instructions' => $definition->instructions,
                'tools' => $definition->tools ?? [],
                ...$this->toolControlConfig($data, $definition),
                ...$this->memoryOverrideConfig($data, $definition),
            ]
            : array_merge($data, $this->toolControlConfig($data, null), $this->memoryOverrideConfig($data, null));

        $generator = $this->agentRunner->streamInline(
            $config,
            $userMessage,
            $definition,
            $threadKey,
            parentRun: $parentRun,
        );

        $emittedKeys = [];

        foreach ($generator as $chunk) {
            if ($chunk instanceof TextChunk && $chunk->content !== '') {
                $state->emitStep('token', [
                    'node_id' => $nodeId,
                    'delta' => $chunk->content,
                ]);

                continue;
            }

            if ($chunk instanceof ToolCallChunk) {
                $key = $this->toolEventKey('call', $chunk->tool->getName(), $chunk->tool->getInputs(), null);
                $emittedKeys[$key] = true;
                $state->emitStep('tool_call', [
                    'node_id' => $nodeId,
                    'name' => $chunk->tool->getName(),
                    'inputs' => $chunk->tool->getInputs() ?? [],
                    'result' => null,
                ]);

                continue;
            }

            if ($chunk instanceof ToolResultChunk) {
                $result = $chunk->tool->getResult();
                $key = $this->toolEventKey('result', $chunk->tool->getName(), $chunk->tool->getInputs(), $result);
                $emittedKeys[$key] = true;
                $state->emitStep('tool_result', [
                    'node_id' => $nodeId,
                    'name' => $chunk->tool->getName(),
                    'inputs' => $chunk->tool->getInputs() ?? [],
                    'result' => $result,
                ]);
            }
        }

        $response = $generator->getReturn();

        $state->set($outputKey, $response->content);
        $this->emitToolEvents($nodeId, $response->toolEvents, $state, $emittedKeys);
        $this->captureRunUsage($state, $response->runId);
        $this->flushContextTruncations($state, $response->runId);

        return 'default';
    }

    protected function flushContextTruncations(WorkflowState $state, ?string $runId): void
    {
        $events = $state->get('__studio_context_truncations');
        $state->set('__studio_context_truncations', null);

        if (! is_array($events) || $events === []) {
            return;
        }

        $this->agentRunner->attachContextTruncationsToRun($runId, $events);
    }

    protected function captureRunUsage(WorkflowState $state, ?string $runId): void
    {
        if ($runId === null) {
            return;
        }

        $run = StudioRun::query()->find($runId);
        if ($run === null) {
            return;
        }

        $state->set('__step_usage', [
            'prompt_tokens' => $run->prompt_tokens ?? 0,
            'completion_tokens' => $run->completion_tokens ?? 0,
            'total_tokens' => $run->total_tokens ?? 0,
            'estimated_cost' => $run->estimated_cost ?? '0.000000',
            'currency' => config('neuronai-studio.usage.currency', 'USD'),
        ]);
    }

    protected function resolveParentRun(WorkflowState $state): ?StudioRun
    {
        $parentId = $state->get('__studio_run_id');
        if (! is_string($parentId) || $parentId === '') {
            return null;
        }

        return StudioRun::query()->find($parentId);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{tool_max_runs?: int, parallel_tool_calls?: bool}
     */
    protected function toolControlConfig(array $data, ?AgentDefinition $definition): array
    {
        $config = [];

        if (array_key_exists('tool_max_runs', $data) && $data['tool_max_runs'] !== null && $data['tool_max_runs'] !== '') {
            $config['tool_max_runs'] = (int) $data['tool_max_runs'];
        } elseif ($definition?->tool_max_runs !== null) {
            $config['tool_max_runs'] = (int) $definition->tool_max_runs;
        }

        if (array_key_exists('parallel_tool_calls', $data) && $data['parallel_tool_calls'] !== null && $data['parallel_tool_calls'] !== '') {
            $config['parallel_tool_calls'] = (bool) $data['parallel_tool_calls'];
        } elseif ($definition?->parallel_tool_calls !== null) {
            $config['parallel_tool_calls'] = (bool) $definition->parallel_tool_calls;
        }

        return $config;
    }

    /**
     * Node-level memory overrides only (empty = inherit agent envelope at makeAgent).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function memoryOverrideConfig(array $data, ?AgentDefinition $definition): array
    {
        unset($definition);

        if (isset($data['memory_config']) && is_array($data['memory_config'])) {
            return array_filter(
                $data['memory_config'],
                static fn ($value) => $value !== null && $value !== '',
            );
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

        $override = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                $override[$key] = $data[$key];
            }
        }

        return $override;
    }

    /**
     * @param  array<int, array<string, mixed>>  $toolEvents
     * @param  array<string, true>  $alreadyEmitted
     */
    protected function emitToolEvents(string $nodeId, array $toolEvents, WorkflowState $state, array $alreadyEmitted = []): void
    {
        if (! $state instanceof BuilderWorkflowState) {
            return;
        }

        foreach ($toolEvents as $event) {
            $type = ($event['type'] ?? '') === 'result' ? 'result' : 'call';
            $key = $this->toolEventKey(
                $type,
                (string) ($event['name'] ?? 'tool'),
                is_array($event['inputs'] ?? null) ? $event['inputs'] : [],
                $event['result'] ?? null,
            );

            if (isset($alreadyEmitted[$key])) {
                continue;
            }

            $eventName = $type === 'result' ? 'tool_result' : 'tool_call';
            $state->emitStep($eventName, [
                'node_id' => $nodeId,
                'name' => $event['name'] ?? 'tool',
                'inputs' => is_array($event['inputs'] ?? null) ? $event['inputs'] : [],
                'result' => $event['result'] ?? null,
            ]);
        }
    }

    protected function toolEventKey(string $type, string $name, mixed $inputs, mixed $result): string
    {
        return $type.'|'.$name.'|'.md5(json_encode([$inputs, $result]));
    }
}
