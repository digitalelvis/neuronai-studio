# Creating & Ingesting Documents

## Create a knowledge base

1. Open **Knowledge Bases** → **New**.
2. Set **name**, optional **description**.
3. Choose **embeddings provider/model** (defaults come from `config/neuronai-studio.php` → `rag.*`).
4. Choose a **vector store** driver and fill driver-specific settings.
5. Optionally override **Top K** and **similarity threshold**.
6. Save.

See [Vector stores](vector-stores.md) for driver credentials and optional packages.

## Ingest

On the edit screen:

- **Upload** — txt, md, pdf, html (max size follows Livewire upload limits)
- **Paste text** — optional document name

Source files (uploads and pasted text) are persisted under `rag.documents_path` on `rag.documents_disk` so you can **Reindex** later.

| Status | Meaning |
|--------|---------|
| `pending` | Queued for async ingest |
| `processing` | Chunking / embedding in progress |
| `completed` | Indexed in the vector store |
| `failed` | See the error on the document row |

### Async vs sync

By default (`NEURONAI_STUDIO_RAG_ASYNC_INGEST=true`) the UI queues `IngestKnowledgeDocumentJob`. Set the env to `false` to run ingest inline (useful for local debugging).

### Chunking

Defaults:

| Config | Env | Default |
|--------|-----|---------|
| `rag.chunk.max_words` | `NEURONAI_STUDIO_RAG_CHUNK_MAX_WORDS` | `200` |
| `rag.chunk.overlap_words` | `NEURONAI_STUDIO_RAG_CHUNK_OVERLAP_WORDS` | `20` |

Empty PDFs (no extractable text) fail with a clear error — paste text or fix extraction (`pdftotext`) instead.

## Reindex & delete

- **Reindex** — removes existing vectors for that document (`deleteBy`) and re-embeds from the stored file.
- **Delete document** — removes vectors and the Eloquent row (and stored source file).
- **Delete knowledge base** — cleans documents, vectors, and the local `.store` file when using the `file` driver.

## Next steps

- [Retrieval preview & RAG node](retrieval.md)
- [Agent binding](agent-binding.md)
