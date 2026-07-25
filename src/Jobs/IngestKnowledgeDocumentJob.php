<?php

namespace DigitalElvis\NeuronAIStudio\Jobs;

use DigitalElvis\NeuronAIStudio\Models\KnowledgeDocument;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\DocumentIngestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class IngestKnowledgeDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public function __construct(
        public int $documentId,
    ) {
        $this->onQueue((string) config('neuronai-studio.queue', 'default'));

        $connection = config('neuronai-studio.queue_connection');
        if (is_string($connection) && $connection !== '') {
            $this->onConnection($connection);
        }

        $this->tries = (int) config('neuronai-studio.queue_tries', 1);
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [(int) config('neuronai-studio.queue_backoff', 30)];
    }

    public function handle(DocumentIngestService $ingest): void
    {
        $document = KnowledgeDocument::query()->with('knowledgeBase')->find($this->documentId);

        if ($document === null || $document->knowledgeBase === null) {
            return;
        }

        $ingest->processRecord($document->knowledgeBase, $document);
    }

    public function failed(?Throwable $exception): void
    {
        $document = KnowledgeDocument::find($this->documentId);

        if ($document === null) {
            return;
        }

        $document->update([
            'status' => KnowledgeDocument::STATUS_FAILED,
            'error' => $exception?->getMessage() ?? 'Document ingest job failed.',
        ]);
    }
}
