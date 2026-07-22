<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\HumanInputRequiredException;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;
use NeuronAI\Workflow\WorkflowState;

class HumanNodeExecutor implements NodeExecutorInterface
{
    /**
     * One-shot resume marker: when set to this node id, a seeded output_key
     * may pass through without interrupting. Cleared on consume.
     * Prevents stale human replies from auto-skipping later loop visits.
     */
    public const PASSTHROUGH_STATE_KEY = '__human_passthrough';

    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = $nodeConfig['data'] ?? [];
        $nodeId = (string) ($nodeConfig['id'] ?? '');
        $outputKey = (string) ($data['output_key'] ?? 'human_response');
        $prompt = StateTemplateInterpolator::interpolate(
            (string) ($data['prompt'] ?? 'Please provide your input.'),
            $state,
        );

        $passthroughFor = $state->get(self::PASSTHROUGH_STATE_KEY);
        if ($passthroughFor === $nodeId) {
            $state->set(self::PASSTHROUGH_STATE_KEY, null);
            $value = $state->get($outputKey);
            if ($value !== null && $value !== '') {
                return 'default';
            }
        }

        // Drop stale replies from earlier HITL visits so loops re-prompt.
        if ($state->get($outputKey) !== null && $state->get($outputKey) !== '') {
            $state->set($outputKey, null);
        }

        throw new HumanInputRequiredException($nodeId, $prompt, $outputKey);
    }
}
