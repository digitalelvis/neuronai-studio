<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Rag;

use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use NeuronAI\RAG\Document;

class RagRetrievalService
{
    public function __construct(
        protected EmbeddingsFactory $embeddings,
        protected VectorStoreFactory $vectorStores,
    ) {}

    /**
     * Retrieve the most relevant chunks for a query.
     *
     * @param  array{top_k?: int|null, threshold?: float|null}  $options
     * @return list<array{id: string|int, content: string, score: float, source_type: string, source_name: string, metadata: array<string, mixed>}>
     */
    public function search(KnowledgeBase $knowledgeBase, string $query, array $options = []): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $topK = $options['top_k'] ?? $knowledgeBase->retrievalDefault('top_k', 5);
        $topK = max(1, (int) $topK);

        $threshold = $options['threshold'] ?? $knowledgeBase->retrievalDefault('threshold');

        $embeddings = $this->embeddings->make($knowledgeBase);
        $store = $this->vectorStores->make($knowledgeBase, ['top_k' => $topK]);

        $queryEmbedding = $embeddings->embedText($query);

        $documents = $store->similaritySearch($queryEmbedding);

        $results = [];

        foreach ($documents as $document) {
            if (! $document instanceof Document) {
                continue;
            }

            $score = $document->getScore();

            if ($threshold !== null && $score < (float) $threshold) {
                continue;
            }

            $results[] = [
                'id' => $document->getId(),
                'content' => $document->getContent(),
                'score' => $score,
                'source_type' => $document->getSourceType(),
                'source_name' => (string) ($document->metadata['document_name'] ?? $document->getSourceName()),
                'metadata' => $document->metadata,
            ];

            if (count($results) >= $topK) {
                break;
            }
        }

        return $results;
    }

    /**
     * Concatenate retrieved chunks into a single context string for prompt injection.
     *
     * @param  list<array{content: string}>  $results
     */
    public function toContext(array $results, string $separator = "\n\n---\n\n"): string
    {
        return implode($separator, array_map(
            static fn (array $result): string => (string) ($result['content'] ?? ''),
            $results,
        ));
    }
}
