# RAG Knowledge Base Tool — Especificação

**Status:** implementado (v0.2.x)

## Overview

Permite criar no Studio uma **ferramenta de agente** (`ToolDefinition` tipo `rag`) vinculada a uma knowledge base. O agent chama a tool com um argumento `query`; o runtime executa retrieval real via `RagRetrievalService` e devolve trechos relevantes como texto.

Complementa o nó `rag` em workflows: aqui o retrieval é **on-demand** via tool calling do LLM, não um passo fixo no grafo.

## Requirements

| ID | Requirement | Priority | Status |
|----|-------------|----------|--------|
| RKT-01 | Aba "RAG - Knowledge Base" em `tools/create` | P0 | done |
| RKT-02 | Seleção de `knowledge_base_id` + top_k/threshold opcionais | P0 | done |
| RKT-03 | `ToolDefinition.type = rag` com `config` persistido | P0 | done |
| RKT-04 | `KnowledgeBaseTool` resolvida em runtime via `ToolResolver` | P0 | done |
| RKT-05 | Tool aparece no agent picker (`tool:db:{id}`, category studio) | P0 | done |
| RKT-06 | Input fixo `query` (string, required) | P0 | done |
| RKT-07 | Retorno texto com trechos prefixados por `source_name` | P1 | done |
| RKT-08 | Export/codegen da RAG tool para classe PHP | P2 | deferred |

## Acceptance Criteria

- Usuário cria tool RAG no Studio, seleciona KB, salva.
- Tool listada em Tools e selecionável no agent edit.
- Agent com a tool anexada pode chamar `search_*` com `query` e receber contexto da KB.
- KB inexistente ou sem resultados retorna mensagem clara (sem crash).

## Documentation

| Arquivo | O que adicionar |
|---------|-----------------|
| `guides/tools/overview.md` | Seção tipo RAG tool |
| `guides/agents/creating-agents.md` | Anexar RAG tool a um agent |
