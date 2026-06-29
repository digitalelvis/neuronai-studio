<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime\NodeExecutors;

use ElvisLopesDigital\NeuronAIStudio\Runtime\GraphContext;
use ElvisLopesDigital\NeuronAIStudio\Runtime\StateTemplateInterpolator;
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
