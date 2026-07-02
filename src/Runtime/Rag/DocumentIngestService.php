<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Rag;

use Closure;
use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Models\KnowledgeDocument;
use NeuronAI\RAG\DataLoader\FileDataLoader;
use NeuronAI\RAG\DataLoader\HtmlReader;
use NeuronAI\RAG\DataLoader\PdfReader;
use NeuronAI\RAG\Document;
use NeuronAI\RAG\Splitter\SentenceTextSplitter;
use NeuronAI\RAG\Splitter\SplitterInterface;
use Throwable;

/**
 * Loads, chunks, embeds and persists documents into a knowledge base vector
 * store, recording a {@see KnowledgeDocument} row with ingest status per source.
 */
class DocumentIngestService
{
    public function __construct(
        protected EmbeddingsFactory $embeddings,
        protected VectorStoreFactory $vectorStores,
    ) {}

    /**
     * Ingest a raw text blob.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function ingestText(
        KnowledgeBase $knowledgeBase,
        string $content,
        string $name = 'text',
        array $metadata = [],
    ): KnowledgeDocument {
        $record = $this->startRecord($knowledgeBase, $name, 'manual', null, $metadata);

        return $this->process($knowledgeBase, $record, function () use ($content): array {
            return $this->splitter()->splitDocument(new Document($content));
        });
    }

    /**
     * Ingest a document from a file path (text, markdown, pdf, html).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function ingestFile(
        KnowledgeBase $knowledgeBase,
        string $path,
        ?string $name = null,
        ?string $storageKey = null,
        array $metadata = [],
    ): KnowledgeDocument {
        $name ??= basename($path);
        $mime = is_file($path) ? (mime_content_type($path) ?: null) : null;

        $record = $this->startRecord($knowledgeBase, $name, 'files', $storageKey, $metadata, $mime);

        return $this->process($knowledgeBase, $record, function () use ($path): array {
            return FileDataLoader::for($path, $this->readers())
                ->withSplitter($this->splitter())
                ->getDocuments();
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function startRecord(
        KnowledgeBase $knowledgeBase,
        string $name,
        string $sourceType,
        ?string $storageKey = null,
        array $metadata = [],
        ?string $mime = null,
    ): KnowledgeDocument {
        return $knowledgeBase->documents()->create([
            'name' => $name,
            'source_type' => $sourceType,
            'storage_key' => $storageKey,
            'mime' => $mime,
            'chunk_count' => 0,
            'status' => KnowledgeDocument::STATUS_PROCESSING,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @param  Closure(): list<Document>  $chunker
     */
    protected function process(KnowledgeBase $knowledgeBase, KnowledgeDocument $record, Closure $chunker): KnowledgeDocument
    {
        try {
            $chunks = $chunker();

            foreach ($chunks as $chunk) {
                $chunk->sourceType = $record->source_type;
                $chunk->sourceName = $record->name;
                $chunk->metadata = array_merge($chunk->metadata, [
                    'knowledge_document_id' => $record->getKey(),
                ]);
            }

            if ($chunks !== []) {
                $embedded = $this->embeddings->make($knowledgeBase)->embedDocuments($chunks);
                $this->vectorStores->make($knowledgeBase)->addDocuments($embedded);
            }

            $record->update([
                'chunk_count' => count($chunks),
                'status' => KnowledgeDocument::STATUS_COMPLETED,
                'error' => null,
            ]);
        } catch (Throwable $e) {
            $record->update([
                'status' => KnowledgeDocument::STATUS_FAILED,
                'error' => $e->getMessage(),
            ]);
        }

        return $record->refresh();
    }

    protected function splitter(): SplitterInterface
    {
        $maxWords = (int) config('neuronai-studio.rag.chunk.max_words', 200);
        $overlap = (int) config('neuronai-studio.rag.chunk.overlap_words', 20);

        if ($overlap >= $maxWords) {
            $overlap = (int) max(0, $maxWords - 1);
        }

        return new SentenceTextSplitter(
            maxWords: $maxWords,
            overlapWords: $overlap,
        );
    }

    /**
     * @return array<string, class-string>
     */
    protected function readers(): array
    {
        return [
            'pdf' => PdfReader::class,
            'html' => HtmlReader::class,
            'htm' => HtmlReader::class,
        ];
    }
}
