<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers;

use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\RagRetrievalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Debug retrieval endpoint consumed by the workflow canvas RAG inspector to
 * preview which chunks a query returns for a given knowledge base.
 */
class KnowledgeBaseSearchController
{
    public function __invoke(
        Request $request,
        KnowledgeBase $knowledgeBase,
        RagRetrievalService $retrieval,
    ): JsonResponse {
        $validated = $request->validate([
            'query' => 'required|string',
            'top_k' => 'nullable|integer|min:1|max:100',
            'threshold' => 'nullable|numeric|min:0|max:1',
        ]);

        try {
            $results = $retrieval->search($knowledgeBase, $validated['query'], [
                'top_k' => $validated['top_k'] ?? null,
                'threshold' => $validated['threshold'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'knowledge_base_id' => $knowledgeBase->getKey(),
            'query' => $validated['query'],
            'chunk_count' => count($results),
            'results' => $results,
        ]);
    }
}
