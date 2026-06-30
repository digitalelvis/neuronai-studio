<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime\NodeExecutors;

use ElvisLopesDigital\NeuronAIStudio\Registry\ProviderRegistry;
use ElvisLopesDigital\NeuronAIStudio\Runtime\GraphContext;
use ElvisLopesDigital\NeuronAIStudio\Runtime\MessageFactory;
use ElvisLopesDigital\NeuronAIStudio\Runtime\StateTemplateInterpolator;
use NeuronAI\Workflow\WorkflowState;

class LlmNodeExecutor implements NodeExecutorInterface
{
    public function __construct(
        protected ProviderRegistry $providers,
        protected MessageFactory $messages,
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

        $aiProvider = $this->providers->resolve($provider, $model);
        $response = $aiProvider->chat($userMessage);

        $state->set($outputKey, $response->getContent());

        return 'default';
    }
}
