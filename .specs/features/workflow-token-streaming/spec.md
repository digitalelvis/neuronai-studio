# Token Streaming em Workflows — Especificação

## Overview

O test harness de workflows emite SSE apenas em limites de step (`step_started`, `step_completed`). Agentes e LLMs dentro de nós bloqueiam até resposta completa. Esta feature propaga **tokens SSE em tempo real** durante execução de nós `agent` e `llm` no workflow test harness — paridade com o agent playground.

## Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| TS-01 | `AgentRunner::streamInline` Generator consumido por `AgentNodeExecutor` em contexto workflow | P0 |
| TS-02 | `LlmNodeExecutor` suporta streaming quando `stream: true` no nó | P0 |
| TS-03 | SSE eventos `token` com `{ node_id, delta, trace_id }` durante step | P0 |
| TS-04 | `WorkflowStreamController` repassa tokens sem buffer excessivo | P0 |
| TS-05 | StudioChat `WorkflowSessionAdapter` agrega tokens em bolha assistant por node | P0 |
| TS-06 | Step boundaries mantidos (`step_started` antes, `step_completed` após stream completo) | P0 |
| TS-07 | Toggle stream no inspector LLM/Agent (default on no harness) | P1 |
| TS-08 | Testes: fake provider stream → N eventos token → step_completed | P0 |

## Acceptance Criteria

- Usuário vê texto aparecendo incrementalmente no chat durante nó agent em workflow test.
- `trace_completed` só após último token e step_completed do último nó.
- Sem regressão em runs com streaming desligado (comportamento blocking atual).
- Human node pause funciona mid-workflow com streaming habilitado.

## Documentation

| Arquivo | O que adicionar |
|---------|-----------------|
| `guides/workflows/runtime-and-traces.md` | Eventos `token` no SSE |
| `guides/agents/playground-and-threads.md` | Paridade streaming agent vs workflow |
| `guides/workflows/node-types/ai-nodes.md` | Opção stream em LLM/Agent |
| `reference/frontend-bundles.md` | WorkflowSessionAdapter token handling |
