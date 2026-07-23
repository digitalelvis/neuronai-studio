# Retrieval & RAG Node

## Search preview

On the knowledge base edit screen, use **Retrieval Preview** to run the same path as runtime (`RagRetrievalService`):

1. Embed the query with the KB embeddings provider
2. `similaritySearch` on the configured vector store
3. Apply `top_k` and optional score `threshold`

Returned chunks show `document_name` (human-readable) and similarity score.

## RAG workflow node

Place a **RAG** node upstream of an Agent or LLM node.

| Config | Description |
|--------|-------------|
| `knowledge_base_id` | Knowledge base ID (required) |
| `query` | Template with `{{state_key}}`; falls back to `input` |
| `top_k` / `threshold` | Override KB defaults |
| `output_key` | State key (default `rag_context`) |

### Output shape

```php
[
    'query' => '...',
    'results' => [/* chunk arrays */],
    'context' => '...', // concatenated text for prompts
    'knowledge_base_id' => 1,
    'chunk_count' => 3,
    'top_score' => 0.91,
]
```

In the agent message template:

```text
Use this context:

{{ rag_context.context }}

User question: {{ input }}
```

Large contexts are truncated by the context-engineering budget (`budget_rag`). See [State & Conditions](../workflows/state-and-conditions.md) and [Runtime & Traces](../workflows/runtime-and-traces.md).

## Node vs tool

| | RAG node | RAG tool |
|--|----------|----------|
| When it runs | Fixed graph step | When the LLM calls the tool |
| Output | Workflow state | Tool result string |
| Typical use | Q&A pipelines | Playground / multi-turn agents |

Details: [Agent binding](agent-binding.md) · [AI Nodes — RAG](../workflows/node-types/ai-nodes.md#rag)
