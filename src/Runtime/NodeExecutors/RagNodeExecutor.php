<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;
use NeuronAI\Workflow\WorkflowState;

class RagNodeExecutor implements NodeExecutorInterface
{
    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = $nodeConfig['data'] ?? [];
        $rawQuery = (string) ($data['query'] ?? $state->get('input', ''));

        if ($rawQuery === '') {
            $rawQuery = (string) $state->get('input', '');
        }

        $query = StateTemplateInterpolator::interpolate($rawQuery, $state);
        $outputKey = $data['output_key'] ?? 'rag_context';

        $state->set($outputKey, [
            'query' => $query,
            'results' => [],
            'note' => 'Configure a RAG class export for production use.',
        ]);

        return 'default';
    }
}
