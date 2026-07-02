# RAG em Workflows — Especificação

## Overview

O nó `rag` existe no canvas mas o `RagNodeExecutor` atual é um stub que grava `results: []` com nota de placeholder. Esta feature entrega **RAG real no Studio**: modelo `KnowledgeBase` (espelhando `AgentDefinition`), executor com ingest e retrieval, nó retrieval-only alimentando agent downstream, e inspector na UI.

Usuários poderão anexar bases de conhecimento a workflows, recuperar contexto relevante antes de um nó agent e inspecionar queries/resultados no editor — padrão essencial para agentes autônomos grounded em documentos.

## Requirements

> **Status de implementação (Fatia 1 — backend):** RAG-01, RAG-03, RAG-04, RAG-05, RAG-09 e RAG-10 entregues; RAG-06 parcial (top_k + threshold ✅, hybrid/MMR ⏳); RAG-02, RAG-07 e RAG-08 pendentes (UI/codegen — Fatias 2 e 3).

| ID | Requirement | Priority | Status |
|----|-------------|----------|--------|
| RAG-01 | Model `KnowledgeBase` com provider embeddings, vector store config, documentos | P0 | ✅ Fatia 1 |
| RAG-02 | CRUD Studio UI para knowledge bases (lista, criar, editar, ingest) | P0 | ⏳ Fatia 2 |
| RAG-03 | `RagNodeExecutor` executa retrieval real via NeuronAI RAG APIs | P0 | ✅ Fatia 1 |
| RAG-04 | Nó `rag` modo retrieval-only: `output_key` com chunks + scores, sem geração | P0 | ✅ Fatia 1 |
| RAG-05 | Ingest pipeline: upload documentos, chunking, embedding, persistência vector store | P0 | ✅ Fatia 1 |
| RAG-06 | Estratégias de retrieval configuráveis (top_k, similarity threshold, hybrid opcional) | P1 | 🟡 parcial (top_k + threshold; hybrid/MMR ⏳) |
| RAG-07 | Inspector do nó rag no canvas: query interpolada, preview resultados no test | P0 | ⏳ Fatia 2 |
| RAG-08 | `RagNodeCodeGenerator` gera classe RAG exportável ou referência `KnowledgeBase` | P1 | ⏳ Fatia 3 |
| RAG-09 | Binding `knowledge_base_id` no nó rag (similar `agent_id`) | P0 | ✅ Fatia 1 |
| RAG-10 | Testes: ingest fixture, retrieval, downstream agent consome `rag_context` | P0 | ✅ Fatia 1 |

## Acceptance Criteria

- Knowledge base criada no Studio com pelo menos um documento ingerido.
- Workflow rag → agent: agent recebe contexto recuperado no prompt/state.
- Test harness mostra no inspector/query log os chunks retornados.
- Export PHP substitui stub TODO por implementação RAG ou referência a classe exportada.
- Falha clara quando knowledge base vazia ou vector store indisponível.

## Documentation ⏳ Fatia 3 (planejado)

_Ainda não escrita — as tabelas já usam nomes sem prefixo (`knowledge_bases`, `knowledge_documents`)._

| Arquivo | O que adicionar |
|---------|-----------------|
| `guides/workflows/node-types/ai-nodes.md` | Seção completa do nó RAG |
| `guides/agents/overview.md` | Cross-link RAG em workflow vs agent RAG class |
| `guides/workflows/overview.md` | Padrão retrieval-only → agent |
| `guides/workflows/runtime-and-traces.md` | Step metadata RAG (query, top_k, chunk count) |
| `reference/database-schema.md` | Tabelas `knowledge_bases`, `knowledge_documents` |
| `reference/configuration.md` | Vector store defaults, embeddings providers |
| `extending/custom-node-types.md` | Exemplo nó que delega a serviço externo |
| `getting-started/quickstart-first-workflow.md` | Mini tutorial RAG + agent |
