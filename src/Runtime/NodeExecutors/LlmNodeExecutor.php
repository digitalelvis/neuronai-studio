<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;
use DigitalElvis\NeuronAIStudio\Runtime\StructuredOutput\StructuredOutputResolver;
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

        if ($data['structured'] ?? false) {
            $outputClass = $this->outputResolver->resolve((string) ($data['output_class'] ?? ''));
            $result = $this->agentRunner->structuredInline([
                'provider' => $provider,
                'model' => $model,
                'instructions' => (string) ($data['instructions'] ?? 'Extract structured data from the user message.'),
            ], $userMessage, $outputClass);

            $state->set($outputKey, $result->structured);

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

            return 'default';
        }

        $response = $aiProvider->chat($userMessage);

        $state->set($outputKey, $response->getContent());

        return 'default';
    }
}
