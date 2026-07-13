<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\ToolApprovalRequiredException;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;
use DigitalElvis\NeuronAIStudio\Runtime\StructuredOutput\StructuredOutputResolver;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
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

        $message = StateTemplateInterpolator::interpolate($rawMessage, $state);
        $attachments = is_array($state->get('attachments')) ? $state->get('attachments') : [];
        $userMessage = $this->messages->resolveMessageWithAttachments($message, $attachments);
        $threadKey = $state->get('__studio_thread_id');
        $threadKey = is_string($threadKey) && $threadKey !== '' ? $threadKey : null;

        $definition = isset($data['agent_id']) ? AgentDefinition::findOrFail($data['agent_id']) : null;

        $resume = $state->get('__tool_approval_resume');
        if (is_array($resume) && ($resume['node_id'] ?? null) === $nodeId) {
            $state->set('__tool_approval_resume', null);

            return $this->resumeApproval($nodeId, (string) $outputKey, $data, $definition, $threadKey, $resume, $state, $context);
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
                ]
                : $data;

            $response = $this->agentRunner->structuredInline($config, $userMessage, $outputClass, $definition, $threadKey);
            $state->set($outputKey, $response->structured);

            return 'default';
        }

        if ($this->shouldStream($data, $requireApproval, $state)) {
            return $this->streamResponse($nodeId, (string) $outputKey, $data, $definition, $userMessage, $threadKey, $state);
        }

        try {
            if ($definition !== null) {
                $response = $this->agentRunner->runInline([
                    'provider' => $definition->provider,
                    'model' => $definition->model,
                    'instructions' => $definition->instructions,
                    'tools' => $definition->tools ?? [],
                    'require_tool_approval' => $requireApproval,
                ], $userMessage, $definition, $threadKey);
            } else {
                $response = $this->agentRunner->runInline(
                    array_merge($data, ['require_tool_approval' => $requireApproval]),
                    $userMessage,
                    null,
                    $threadKey,
                );
            }
        } catch (ToolApprovalRequiredException $exception) {
            throw new ToolApprovalRequiredException($nodeId, $exception->pendingTools, $exception->approvalMessage, $exception->serializedInterrupt);
        }

        $state->set($outputKey, $response->content);
        $this->emitToolEvents($nodeId, $response->toolEvents, $state);

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
            ]
            : array_merge($data, ['require_tool_approval' => true]);

        try {
            $response = $this->agentRunner->resumeInlineApproval($config, $serialized, $decision, $feedback, $definition, $threadKey);
        } catch (ToolApprovalRequiredException $exception) {
            throw new ToolApprovalRequiredException($nodeId, $exception->pendingTools, $exception->approvalMessage, $exception->serializedInterrupt);
        }

        $state->set($outputKey, $response->content);
        $this->emitToolEvents($nodeId, $response->toolEvents, $state);

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
    ): string {
        $config = $definition !== null
            ? [
                'provider' => $definition->provider,
                'model' => $definition->model,
                'instructions' => $definition->instructions,
                'tools' => $definition->tools ?? [],
            ]
            : $data;

        $generator = $this->agentRunner->streamInline($config, $userMessage, $definition, $threadKey);

        foreach ($generator as $chunk) {
            if ($chunk instanceof TextChunk && $chunk->content !== '') {
                $state->emitStep('token', [
                    'node_id' => $nodeId,
                    'delta' => $chunk->content,
                ]);
            }
        }

        $response = $generator->getReturn();

        $state->set($outputKey, $response->content);
        $this->emitToolEvents($nodeId, $response->toolEvents, $state);

        return 'default';
    }

    /**
     * @param  array<int, array<string, mixed>>  $toolEvents
     */
    protected function emitToolEvents(string $nodeId, array $toolEvents, WorkflowState $state): void
    {
        if (! $state instanceof BuilderWorkflowState) {
            return;
        }

        foreach ($toolEvents as $event) {
            $eventName = ($event['type'] ?? '') === 'result' ? 'tool_result' : 'tool_call';
            $state->emitStep($eventName, [
                'node_id' => $nodeId,
                'name' => $event['name'] ?? 'tool',
                'inputs' => is_array($event['inputs'] ?? null) ? $event['inputs'] : [],
                'result' => $event['result'] ?? null,
            ]);
        }
    }
}
