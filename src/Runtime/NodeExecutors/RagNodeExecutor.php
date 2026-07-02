<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\RagRetrievalService;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;
use NeuronAI\Workflow\WorkflowState;
use RuntimeException;

class RagNodeExecutor implements NodeExecutorInterface
{
    public function __construct(
        protected RagRetrievalService $retrieval,
    ) {}

    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $nodeId = (string) ($nodeConfig['id'] ?? 'rag');
        $data = $nodeConfig['data'] ?? [];
        $outputKey = $data['output_key'] ?? 'rag_context';

        $rawQuery = (string) ($data['query'] ?? '');
        if ($rawQuery === '') {
            $rawQuery = (string) $state->get('input', '');
        }
        $query = StateTemplateInterpolator::interpolate($rawQuery, $state);

        $knowledgeBaseId = $data['knowledge_base_id'] ?? null;
        if (empty($knowledgeBaseId)) {
            throw new RuntimeException('RAG node requires a knowledge_base_id.');
        }

        $knowledgeBase = KnowledgeBase::findOrFail($knowledgeBaseId);

        $results = $this->retrieval->search($knowledgeBase, $query, [
            'top_k' => $data['top_k'] ?? null,
            'threshold' => $data['threshold'] ?? null,
        ]);

        $contextText = $this->retrieval->toContext($results);
        $topScore = $results !== [] ? (float) $results[0]['score'] : 0.0;

        $state->set($outputKey, [
            'query' => $query,
            'results' => $results,
            'context' => $contextText,
            'knowledge_base_id' => $knowledgeBase->getKey(),
            'chunk_count' => count($results),
            'top_score' => $topScore,
        ]);

        if ($state instanceof BuilderWorkflowState) {
            $state->emitStep('rag_query', [
                'node_id' => $nodeId,
                'query' => $query,
                'knowledge_base_id' => $knowledgeBase->getKey(),
                'chunk_count' => count($results),
                'top_score' => $topScore,
            ]);
        }

        return 'default';
    }
}
