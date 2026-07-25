<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Rag;

use NeuronAI\RAG\VectorStore\FileVectorStore;
use NeuronAI\RAG\VectorStore\VectorStoreInterface;

/**
 * File-backed vector store that tolerates a missing store file (empty KB or
 * ingest that produced no chunks) instead of crashing on fopen.
 */
class StudioFileVectorStore extends FileVectorStore
{
    public function similaritySearch(array $embedding): array
    {
        if (! is_readable($this->getFilePath())) {
            return [];
        }

        return parent::similaritySearch($embedding);
    }

    public function deleteBy(string $sourceType, ?string $sourceName = null): VectorStoreInterface
    {
        if (! is_readable($this->getFilePath())) {
            return $this;
        }

        return parent::deleteBy($sourceType, $sourceName);
    }
}
