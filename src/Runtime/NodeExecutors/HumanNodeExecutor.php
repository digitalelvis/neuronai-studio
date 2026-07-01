<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\HumanInputRequiredException;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;
use NeuronAI\Workflow\WorkflowState;

class HumanNodeExecutor implements NodeExecutorInterface
{
    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = $nodeConfig['data'] ?? [];
        $nodeId = (string) ($nodeConfig['id'] ?? '');
        $outputKey = (string) ($data['output_key'] ?? 'human_response');
        $prompt = StateTemplateInterpolator::interpolate(
            (string) ($data['prompt'] ?? 'Please provide your input.'),
            $state,
        );

        if ($state->get($outputKey) !== null && $state->get($outputKey) !== '') {
            return 'default';
        }

        throw new HumanInputRequiredException($nodeId, $prompt, $outputKey);
    }
}
