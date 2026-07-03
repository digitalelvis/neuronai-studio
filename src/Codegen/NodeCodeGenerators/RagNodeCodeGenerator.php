<?php

namespace DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators;

class RagNodeCodeGenerator implements NodeCodeGeneratorInterface
{
    public function supports(string $type): bool
    {
        return $type === 'rag';
    }

    public function generate(array $nodePlan, CodegenContext $context): array
    {
        $data = $nodePlan['data'];
        $query = var_export((string) ($data['query'] ?? ''), true);
        $outputKey = var_export((string) ($data['output_key'] ?? 'rag_context'), true);
        $return = $context->returnStatement($nodePlan['returnType']);

        if (empty($data['knowledge_base_id'])) {
            throw new \InvalidArgumentException('RAG node requires knowledge_base_id for native export.');
        }

        $knowledgeBaseId = (int) $data['knowledge_base_id'];
        $searchOptions = $this->searchOptionsExpression($data);

        $body = <<<PHP
        \$template = {$query};
        \$query = StateTemplateInterpolator::interpolate(\$template, \$state);
        if (\$query === '') {
            \$query = (string) \$state->get('input', '');
        }

        \$knowledgeBase = KnowledgeBase::findOrFail({$knowledgeBaseId});
        \$retrieval = app(RagRetrievalService::class);
        \$results = \$retrieval->search(\$knowledgeBase, \$query, {$searchOptions});
        \$contextText = \$retrieval->toContext(\$results);
        \$topScore = \$results !== [] ? (float) \$results[0]['score'] : 0.0;

        \$state->set({$outputKey}, [
            'query' => \$query,
            'results' => \$results,
            'context' => \$contextText,
            'knowledge_base_id' => \$knowledgeBase->getKey(),
            'chunk_count' => count(\$results),
            'top_score' => \$topScore,
        ]);

        {$return}
PHP;

        return [
            'body' => $body,
            'imports' => [
                'DigitalElvis\\NeuronAIStudio\\Models\\KnowledgeBase',
                'DigitalElvis\\NeuronAIStudio\\Runtime\\Rag\\RagRetrievalService',
                'DigitalElvis\\NeuronAIStudio\\Runtime\\StateTemplateInterpolator',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function searchOptionsExpression(array $data): string
    {
        $options = [];

        if (array_key_exists('top_k', $data) && $data['top_k'] !== null && $data['top_k'] !== '') {
            $options['top_k'] = (int) $data['top_k'];
        }

        if (array_key_exists('threshold', $data) && $data['threshold'] !== null && $data['threshold'] !== '') {
            $options['threshold'] = (float) $data['threshold'];
        }

        return var_export($options, true);
    }
}
