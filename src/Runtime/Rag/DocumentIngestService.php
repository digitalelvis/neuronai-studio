<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Rag;

use Closure;
use DigitalElvis\NeuronAIStudio\Jobs\IngestKnowledgeDocumentJob;
use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Models\KnowledgeDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
     * Ingest a raw text blob synchronously.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function ingestText(
        KnowledgeBase $knowledgeBase,
        string $content,
        string $name = 'text',
        array $metadata = [],
    ): KnowledgeDocument {
        $storageKey = $this->persistText($knowledgeBase, $content, $name);
        $record = $this->startRecord($knowledgeBase, $name, 'manual', $storageKey, $metadata);

        return $this->processRecord($knowledgeBase, $record);
    }

    /**
     * Queue text ingest (creates a pending document and dispatches a job).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function queueText(
        KnowledgeBase $knowledgeBase,
        string $content,
        string $name = 'text',
        array $metadata = [],
    ): KnowledgeDocument {
        $storageKey = $this->persistText($knowledgeBase, $content, $name);
        $record = $this->startRecord(
            $knowledgeBase,
            $name,
            'manual',
            $storageKey,
            $metadata,
            status: KnowledgeDocument::STATUS_PENDING,
        );

        IngestKnowledgeDocumentJob::dispatch($record->getKey());

        return $record;
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

        if ($storageKey === null && is_file($path)) {
            $storageKey = $this->persistPath($knowledgeBase, $path, $name);
        }

        $record = $this->startRecord($knowledgeBase, $name, 'files', $storageKey, $metadata, $mime);

        return $this->processRecord($knowledgeBase, $record);
    }

    /**
     * Persist an uploaded file and queue ingest.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function queueUpload(
        KnowledgeBase $knowledgeBase,
        UploadedFile $upload,
        array $metadata = [],
    ): KnowledgeDocument {
        $name = $upload->getClientOriginalName() ?: 'upload';
        $storageKey = $this->persistUpload($knowledgeBase, $upload, $name);
        $mime = $upload->getMimeType();

        $record = $this->startRecord(
            $knowledgeBase,
            $name,
            'files',
            $storageKey,
            $metadata,
            $mime,
            KnowledgeDocument::STATUS_PENDING,
        );

        IngestKnowledgeDocumentJob::dispatch($record->getKey());

        return $record;
    }

    /**
     * Process (or re-process) an existing document row from its storage_key.
     */
    public function processRecord(KnowledgeBase $knowledgeBase, KnowledgeDocument $record): KnowledgeDocument
    {
        $record->update([
            'status' => KnowledgeDocument::STATUS_PROCESSING,
            'error' => null,
        ]);

        return $this->process($knowledgeBase, $record, function () use ($record): array {
            $path = $this->absolutePath($record);

            if ($path === null || ! is_file($path)) {
                throw new \RuntimeException(
                    'Document source file is missing. Re-upload the document or paste the text again.'
                );
            }

            if ($record->source_type === 'manual') {
                $content = (string) file_get_contents($path);

                return $this->splitter()->splitDocument(new Document($content));
            }

            return FileDataLoader::for($path, $this->readers())
                ->withSplitter($this->splitter())
                ->getDocuments();
        });
    }

    /**
     * Remove vectors for the document then re-embed from storage.
     */
    public function reindex(KnowledgeDocument $document): KnowledgeDocument
    {
        $knowledgeBase = $document->knowledgeBase;
        $this->deleteVectors($knowledgeBase, $document);

        $document->update([
            'chunk_count' => 0,
            'status' => KnowledgeDocument::STATUS_PENDING,
            'error' => null,
        ]);

        return $this->processRecord($knowledgeBase, $document->refresh());
    }

    /**
     * Queue a reindex for a document.
     */
    public function queueReindex(KnowledgeDocument $document): KnowledgeDocument
    {
        $knowledgeBase = $document->knowledgeBase;
        $this->deleteVectors($knowledgeBase, $document);

        $document->update([
            'chunk_count' => 0,
            'status' => KnowledgeDocument::STATUS_PENDING,
            'error' => null,
        ]);

        IngestKnowledgeDocumentJob::dispatch($document->getKey());

        return $document->refresh();
    }

    /**
     * Delete vectors then the Eloquent row (and optional stored file).
     */
    public function removeDocument(KnowledgeDocument $document, bool $deleteStoredFile = true): void
    {
        $knowledgeBase = $document->knowledgeBase;
        $this->deleteVectors($knowledgeBase, $document);

        if ($deleteStoredFile && $document->storage_key) {
            Storage::disk($this->disk())->delete($document->storage_key);
        }

        $document->delete();
    }

    /**
     * Delete all documents/vectors and the file store for a knowledge base.
     */
    public function removeKnowledgeBase(KnowledgeBase $knowledgeBase): void
    {
        foreach ($knowledgeBase->documents()->cursor() as $document) {
            $this->deleteVectors($knowledgeBase, $document);

            if ($document->storage_key) {
                Storage::disk($this->disk())->delete($document->storage_key);
            }
        }

        if ($knowledgeBase->vectorStoreDriver() === 'file') {
            $storePath = $this->vectorStores->fileStorePath($knowledgeBase);

            if (is_file($storePath)) {
                @unlink($storePath);
            }
        }

        $dir = $this->relativeDirectory($knowledgeBase);

        if (Storage::disk($this->disk())->exists($dir)) {
            Storage::disk($this->disk())->deleteDirectory($dir);
        }

        $knowledgeBase->delete();
    }

    public function deleteVectors(KnowledgeBase $knowledgeBase, KnowledgeDocument $document): void
    {
        try {
            $this->vectorStores->make($knowledgeBase)->deleteBy(
                $document->source_type,
                $document->vectorSourceName(),
            );
        } catch (Throwable) {
            // Store may be empty / unreachable — still allow DB cleanup.
        }
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
        string $status = KnowledgeDocument::STATUS_PROCESSING,
    ): KnowledgeDocument {
        return $knowledgeBase->documents()->create([
            'name' => $name,
            'source_type' => $sourceType,
            'storage_key' => $storageKey,
            'mime' => $mime,
            'chunk_count' => 0,
            'status' => $status,
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

            if ($chunks === []) {
                throw new \RuntimeException(
                    'No text chunks were extracted from the document. '
                    .'Check the file format, PDF text extraction (pdftotext), or try pasting plain text.'
                );
            }

            $sourceName = $record->vectorSourceName();

            foreach ($chunks as $chunk) {
                $chunk->sourceType = $record->source_type;
                $chunk->sourceName = $sourceName;
                $chunk->metadata = array_merge($chunk->metadata, [
                    'knowledge_document_id' => $record->getKey(),
                    'document_name' => $record->name,
                ]);
            }

            $this->deleteVectors($knowledgeBase, $record);

            $embedded = $this->embeddings->make($knowledgeBase)->embedDocuments($chunks);
            $this->vectorStores->make($knowledgeBase)->addDocuments($embedded);

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

    protected function persistText(KnowledgeBase $knowledgeBase, string $content, string $name): string
    {
        $safe = $this->safeFilename($name, 'txt');
        $key = $this->relativeDirectory($knowledgeBase).'/'.uniqid('txt_', true).'_'.$safe;
        Storage::disk($this->disk())->put($key, $content);

        return $key;
    }

    protected function persistPath(KnowledgeBase $knowledgeBase, string $path, string $name): string
    {
        $ext = pathinfo($name, PATHINFO_EXTENSION) ?: pathinfo($path, PATHINFO_EXTENSION) ?: 'bin';
        $safe = $this->safeFilename($name, $ext);
        $key = $this->relativeDirectory($knowledgeBase).'/'.uniqid('file_', true).'_'.$safe;
        Storage::disk($this->disk())->put($key, file_get_contents($path) ?: '');

        return $key;
    }

    protected function persistUpload(KnowledgeBase $knowledgeBase, UploadedFile $upload, string $name): string
    {
        $ext = $upload->getClientOriginalExtension() ?: 'bin';
        $safe = $this->safeFilename($name, $ext);
        $directory = $this->relativeDirectory($knowledgeBase);

        return $upload->storeAs($directory, uniqid('up_', true).'_'.$safe, $this->disk());
    }

    protected function absolutePath(KnowledgeDocument $record): ?string
    {
        if (! $record->storage_key) {
            return null;
        }

        return Storage::disk($this->disk())->path($record->storage_key);
    }

    protected function relativeDirectory(KnowledgeBase $knowledgeBase): string
    {
        $root = trim((string) config('neuronai-studio.rag.documents_path', 'neuronai-studio/knowledge-documents'), '/');

        return $root.'/'.($knowledgeBase->getKey() ?? 'tmp');
    }

    protected function disk(): string
    {
        return (string) config('neuronai-studio.rag.documents_disk', 'local');
    }

    protected function safeFilename(string $name, string $fallbackExt): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME) ?: 'document';
        $ext = pathinfo($name, PATHINFO_EXTENSION) ?: $fallbackExt;
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base) ?: 'document';

        return $safe.'.'.$ext;
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
