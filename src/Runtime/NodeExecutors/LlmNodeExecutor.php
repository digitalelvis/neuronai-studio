<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioTrace;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;
use DigitalElvis\NeuronAIStudio\Runtime\StructuredOutput\StructuredOutputResolver;
use DigitalElvis\NeuronAIStudio\Usage\UsageRecorder;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Workflow\WorkflowState;

class LlmNodeExecutor implements NodeExecutorInterface
{
    public function __construct(
        protected ProviderRegistry $providers,
        protected AgentRunner $agentRunner,
        protected MessageFactory $messages,
        protected StructuredOutputResolver $outputResolver,
        protected UsageRecorder $usageRecorder = new UsageRecorder,
    ) {}

    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = $nodeConfig['data'] ?? [];
        $provider = $data['provider'] ?? config('neuronai-studio.default_provider');
        $model = $data['model'] ?? config('neuronai-studio.default_model');
        $prompt = StateTemplateInterpolator::interpolate(
            (string) ($data['prompt'] ?? $state->get('input', '')),
            $state,
        );
        $outputKey = $data['output_key'] ?? 'llm_response';

        $attachments = is_array($state->get('attachments')) ? $state->get('attachments') : [];
        $userMessage = $this->messages->resolveMessageWithAttachments((string) $prompt, $attachments);
        $threadKey = $this->resolveThreadKey($state);
        $parentRun = $this->resolveParentRun($state);

        if ($data['structured'] ?? false) {
            $outputClass = $this->outputResolver->resolve((string) ($data['output_class'] ?? ''));
            $result = $this->agentRunner->structuredInline([
                'provider' => $provider,
                'model' => $model,
                'instructions' => (string) ($data['instructions'] ?? 'Extract structured data from the user message.'),
            ], $userMessage, $outputClass, threadKey: $threadKey, parentRun: $parentRun);

            $state->set($outputKey, $result->structured);
            $this->captureRunUsage($state, $result->runId);

            return 'default';
        }

        $aiProvider = $this->providers->resolve($provider, $model);

        if (($data['stream'] ?? false) === true && $state instanceof BuilderWorkflowState && $state->stepEmitter !== null) {
            $nodeId = (string) ($nodeConfig['id'] ?? 'llm');
            $generator = $aiProvider->stream($userMessage);

            foreach ($generator as $chunk) {
                if ($chunk instanceof TextChunk && $chunk->content !== '') {
                    $state->emitStep('token', [
                        'node_id' => $nodeId,
                        'delta' => $chunk->content,
                    ]);
                }
            }

            /** @var Message $finalMessage */
            $finalMessage = $generator->getReturn();
            $state->set($outputKey, $finalMessage->getContent());
            $this->recordUsage($state, (string) $provider, (string) $model, $finalMessage);

            return 'default';
        }

        $response = $aiProvider->chat($userMessage);

        $state->set($outputKey, $response->getContent());
        $this->recordUsage($state, (string) $provider, (string) $model, $response);

        return 'default';
    }

    protected function recordUsage(WorkflowState $state, string $provider, string $model, Message $message): void
    {
        $run = $this->resolveParentRun($state);
        $trace = $this->resolveParentTrace($state);

        if ($run === null || $trace === null) {
            return;
        }

        $usage = $message->getUsage();
        $promptTokens = $usage ? $usage->inputTokens : 0;
        $completionTokens = $usage ? $usage->outputTokens : 0;

        $span = $this->usageRecorder->recordLlmSpan(
            $run,
            $trace,
            $provider,
            $model,
            $promptTokens,
            $completionTokens,
        );

        $state->set('__step_usage', [
            'prompt_tokens' => $span->prompt_tokens,
            'completion_tokens' => $span->completion_tokens,
            'total_tokens' => $span->total_tokens,
            'estimated_cost' => $span->estimated_cost,
            'currency' => config('neuronai-studio.usage.currency', 'USD'),
            'provider' => $span->provider,
            'model' => $span->model,
        ]);
    }

    protected function resolveParentRun(WorkflowState $state): ?StudioRun
    {
        $runId = $state->get('__studio_run_id');
        if (! is_string($runId) || $runId === '') {
            return null;
        }

        return StudioRun::query()->find($runId);
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

    protected function resolveParentTrace(WorkflowState $state): ?StudioTrace
    {
        $traceId = $state->get('__studio_trace_id') ?? $state->get('__workflow_trace_id');
        if (! is_string($traceId) || $traceId === '') {
            return null;
        }

        return StudioTrace::query()->find($traceId);
    }

    protected function resolveThreadKey(WorkflowState $state): ?string
    {
        $threadKey = $state->get('__studio_thread_id');

        return is_string($threadKey) && $threadKey !== '' ? $threadKey : null;
    }
}
