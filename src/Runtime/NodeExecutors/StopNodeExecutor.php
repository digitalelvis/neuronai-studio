<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowReplyResolver;
use NeuronAI\Workflow\WorkflowState;

class StopNodeExecutor implements NodeExecutorInterface
{
    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = is_array($nodeConfig['data'] ?? null) ? $nodeConfig['data'] : [];
        $template = $data['reply'] ?? null;

        if (! is_string($template) || trim($template) === '') {
            return 'default';
        }

        $interpolated = StateTemplateInterpolator::interpolate($template, $state);
        $state->set(WorkflowReplyResolver::STATE_KEY, $interpolated);

        return 'default';
    }
}
