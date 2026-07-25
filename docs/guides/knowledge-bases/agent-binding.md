# Bind Knowledge Bases to Agents

Agents do **not** auto-attach a knowledge base. Connect retrieval explicitly with a RAG tool or a workflow RAG node.

## RAG tool (`KnowledgeBaseTool`)

1. Create a tool: **Tools** → **Create**, choose kind **RAG** (`?kind=rag`).
2. Select the **knowledge base**, optional `top_k` / `threshold`.
3. Bind the tool to an agent (agent editor → tool bindings).
4. Open the Playground — the model can call the tool with a `query` string.

The tool resolves via `ToolDefinition.type === 'rag'` and returns source-prefixed chunks, or a clear message when nothing matches.

| Need | Use |
|------|-----|
| Agent decides when to search | RAG tool |
| Always inject context before the agent | [RAG node](retrieval.md) |

## Template: RAG Knowledge Q&A

Install workflow template `rag-knowledge-qna` (and agent `knowledge-agent`):

1. **Templates** → use **RAG Knowledge Q&A**
2. Open the RAG node → select your knowledge base
3. Run the workflow — the agent prompt uses `{{ rag_context.context }}`

Related: `support-rag-hitl` and `dev-support-memory-loop` also include RAG nodes.

## Quick checklist

- [ ] Knowledge base created and documents `completed`
- [ ] Retrieval preview returns sensible chunks
- [ ] Either RAG tool bound to the agent **or** RAG node wired in the graph
- [ ] Agent instructions tell the model to use retrieved context / the search tool

See also: [Tools Overview](../tools/overview.md#rag-knowledge-base-tool) · [Templates](../templates.md)
