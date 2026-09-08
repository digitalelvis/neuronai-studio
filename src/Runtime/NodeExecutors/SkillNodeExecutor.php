<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use NeuronAI\Workflow\WorkflowState;

/**
 * Skill nodes are binding-only (connected to agent skills handle).
 * If reached via control flow, record a clear error in state.
 */
class SkillNodeExecutor implements NodeExecutorInterface
{
    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = $nodeConfig['data'] ?? [];
        $outputKey = $data['output_key'] ?? 'skill_result';

        $state->set($outputKey, [
            'error' => 'Skill nodes are binding-only. Connect this node to an agent skills handle instead of a control-flow path.',
        ]);

        return 'default';
    }
}
