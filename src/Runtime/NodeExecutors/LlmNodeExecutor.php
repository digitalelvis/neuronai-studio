<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime\NodeExecutors;

use ElvisLopesDigital\NeuronAIStudio\Registry\ProviderRegistry;
use ElvisLopesDigital\NeuronAIStudio\Runtime\GraphContext;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Workflow\WorkflowState;

class LlmNodeExecutor implements NodeExecutorInterface
{
    public function __construct(
        protected ProviderRegistry $providers,
    ) {}

    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = $nodeConfig['data'] ?? [];
        $provider = $data['provider'] ?? config('neuronai-studio.default_provider');
        $model = $data['model'] ?? config('neuronai-studio.default_model');
        $prompt = $data['prompt'] ?? $state->get('input', '');
        $outputKey = $data['output_key'] ?? 'llm_response';

        $aiProvider = $this->providers->resolve($provider, $model);
        $response = $aiProvider->chat(new UserMessage((string) $prompt));

        $state->set($outputKey, $response->getContent());

        return 'default';
    }
}
