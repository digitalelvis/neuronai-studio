<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use NeuronAI\Workflow\WorkflowState;

/**
 * Merges the results collected by the paired fork node into a single output key
 * ({ branchId: result, ... }) and clears the parallel scratch state.
 */
class JoinNodeExecutor implements NodeExecutorInterface
{
    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = $nodeConfig['data'] ?? [];
        $outputKey = (string) ($data['output_key'] ?? 'parallel_results');

        $parallel = $state->get('__parallel_results');
        $results = is_array($parallel) && is_array($parallel['results'] ?? null)
            ? $parallel['results']
            : [];

        $state->set($outputKey, $results);
        $state->set('__parallel_results', null);

        return 'default';
    }
}
