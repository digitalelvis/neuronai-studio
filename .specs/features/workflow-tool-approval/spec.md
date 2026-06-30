# Tool Approval em Workflows — Especificação

## Overview

Agentes em workflows podem invocar ferramentas destrutivas ou sensíveis. Esta feature integra o middleware **ToolApproval** do NeuronAI nos nós agent: execução pausa até aprovação humana, com resume via trace/HITL no StudioChat — mesmo padrão do nó Human, porém acoplado a tool calls.

## Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| TA-01 | Config `require_tool_approval` por AgentDefinition ou override no nó agent | P0 |
| TA-02 | `AgentRunner` aplica middleware `ToolApproval` quando habilitado | P0 |
| TA-03 | Tool pendente → `ToolApprovalRequiredException` com payload tool name, args, node_id | P0 |
| TA-04 | `WorkflowRunner` persiste checkpoint `awaiting_tool_approval` no trace | P0 |
| TA-05 | Resume via `POST /workflows/runs/{run}/resume/stream` com `approval: approve\|reject` | P0 |
| TA-06 | StudioChat renderiza card de aprovação inline (sem modal) | P0 |
| TA-07 | Rejeição roteia para handle `rejected` opcional no nó agent | P1 |
| TA-08 | Codegen inclui `ToolApproval` middleware no export | P1 |
| TA-09 | Testes: fake tool → pause → approve → continua workflow | P0 |

## Acceptance Criteria

- Workflow com agent + calculator pausa antes de executar tool quando approval on.
- Usuário aprova no chat; workflow retoma do mesmo nó sem re-executar steps anteriores.
- Trace status `awaiting_tool_approval` distinto de `awaiting_input` (Human).
- Rejeição encerra ou ramifica conforme configuração do grafo.

## Documentation

| Arquivo | O que adicionar |
|---------|-----------------|
| `guides/workflows/human-in-the-loop.md` | Seção Tool Approval vs Human node |
| `guides/workflows/node-types/ai-nodes.md` | Config approval no agent node |
| `guides/agents/creating-agents.md` | ToolApproval no agent definition |
| `guides/workflows/runtime-and-traces.md` | Status `awaiting_tool_approval`, resume payload |
| `guides/security-and-access.md` | Implicações de aprovar tools sensíveis |
