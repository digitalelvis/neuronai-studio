<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;
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
        $prompt = StateTemplateInterpolator::interpolate(
            (string) ($data['prompt'] ?? $state->get('input', '')),
            $state,
        );
        $outputKey = $data['output_key'] ?? 'llm_response';

        $aiProvider = $this->providers->resolve($provider, $model);
        $response = $aiProvider->chat(new UserMessage((string) $prompt));

        $state->set($outputKey, $response->getContent());

        return 'default';
    }
}
