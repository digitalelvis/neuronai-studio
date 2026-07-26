# Knowledge bases & RAG

## What

Embedded chunks in a vector store. Retrieval via `RagRetrievalService` — **not** Neuron’s `RAG` agent subclass.

| Pattern | Use |
|---------|-----|
| RAG **node** | Fixed grounding in a workflow (`state.rag_context`) |
| RAG **tool** | Agent decides when to search (Playground / chat) |

## Workflow

1. Create KB: embeddings + vector store driver + `top_k` / `threshold`
2. Ingest documents; reindex if needed
3. Preview search in Studio
4. Bind: workflow RAG node and/or agent RAG tool

## Checklist

- [ ] Vector store package installed if using ES/OpenSearch/Typesense/php-vector
- [ ] Documents path/disk from `rag.*` config
- [ ] Do not invent Neuron `RAG` agent subclass for Studio flows

## Documentation (canonical)

- [Overview](../../../docs/guides/knowledge-bases/overview.md)
- [Creating & Ingest](../../../docs/guides/knowledge-bases/creating-and-ingest.md)
- [Vector Stores](../../../docs/guides/knowledge-bases/vector-stores.md)
- [Retrieval & RAG Node](../../../docs/guides/knowledge-bases/retrieval.md)
- [Agent Binding](../../../docs/guides/knowledge-bases/agent-binding.md)
