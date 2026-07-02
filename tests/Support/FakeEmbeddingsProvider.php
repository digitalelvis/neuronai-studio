<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Support;

use NeuronAI\RAG\Document;
use NeuronAI\RAG\Embeddings\AbstractEmbeddingsProvider;

/**
 * Deterministic, network-free embeddings for tests. Produces a normalized
 * bag-of-words vector so texts sharing words score as similar under cosine.
 */
class FakeEmbeddingsProvider extends AbstractEmbeddingsProvider
{
    public function __construct(private readonly int $dimensions = 512) {}

    public function embedText(string $text): array
    {
        $vector = array_fill(0, $this->dimensions, 0.0);

        $words = preg_split('/\W+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($words as $word) {
            $index = abs(crc32($word)) % $this->dimensions;
            $vector[$index] += 1.0;
        }

        $norm = sqrt(array_sum(array_map(static fn (float $v): float => $v * $v, $vector)));

        if ($norm > 0.0) {
            $vector = array_map(static fn (float $v): float => $v / $norm, $vector);
        }

        return $vector;
    }

    public function embedDocuments(array $documents): array
    {
        foreach ($documents as $document) {
            /** @var Document $document */
            $document->embedding = $this->embedText($document->getContent());
        }

        return $documents;
    }
}
