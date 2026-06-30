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

        $body = <<<PHP
        \$template = {$query};
        \$query = {$context->interpolate('$template')};
        if (\$query === '') {
            \$query = (string) \$state->get('input', '');
        }

        // TODO: Replace with your RAG pipeline (RetrievalNode, vector store, etc.)
        \$state->set({$outputKey}, [
            'query' => \$query,
            'results' => [],
            'note' => 'Configure a RAG class export for production use.',
        ]);

        {$return}
PHP;

        return ['body' => $body, 'imports' => []];
    }
}
