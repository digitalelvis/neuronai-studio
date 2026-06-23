<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime\NodeExecutors;

use ElvisLopesDigital\NeuronAIStudio\Runtime\GraphContext;
use NeuronAI\Workflow\WorkflowState;

class ToolNodeExecutor implements NodeExecutorInterface
{
    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = $nodeConfig['data'] ?? [];
        $toolClass = $data['tool_class'] ?? null;
        $outputKey = $data['output_key'] ?? 'tool_result';

        if ($toolClass && class_exists($toolClass)) {
            $tool = app($toolClass);
            $result = method_exists($tool, 'execute')
                ? $tool->execute($data['parameters'] ?? [])
                : ['status' => 'executed', 'tool' => $toolClass];
            $state->set($outputKey, $result);
        } else {
            $state->set($outputKey, ['error' => 'Tool class not found or not configured.']);
        }

        return 'default';
    }
}
