# Agentes Multimodais Autônomos — Especificação

## Overview

Feature guarda-chuva que une **grafos cíclicos**, **nós agent** com tool calling e memória, e **anexos multimodais** (imagem, áudio, vídeo, PDF) fluindo pelo estado do workflow. O objetivo é permitir agentes que operam de forma autônoma em múltiplas voltas do grafo — analisando mídia, chamando ferramentas, refinando respostas — sem parada rígida após cada nó.

O usuário ganha um padrão end-to-end no Studio: subir um documento ou foto no test harness, executar um workflow cíclico onde o agente itera até qualificar um lead, aprovar dados ou esgotar `max_steps`, com histórico de conversa persistido entre iterações.

## Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| AMA-01 | Anexos do composer fluem para `state.attachments` em runs de workflow e sobrevivem entre iterações do loop | P0 |
| AMA-02 | `MessageFactory` usado por `AgentNodeExecutor` e `LlmNodeExecutor` para montar `UserMessage` multimodal | P0 |
| AMA-03 | `__studio_thread_id` estável por run/trace; agent nodes no mesmo loop compartilham thread | P0 |
| AMA-04 | Agent nodes executam com tools + memory_config do `AgentDefinition` sem hard stop após primeira resposta | P0 |
| AMA-05 | Estado propagado: `output_key` do agent alimenta condition/loop na mesma iteração ou próxima | P0 |
| AMA-06 | Workflow template `autonomous-lead-qualification` combina loop + agent + attachments + condition | P0 |
| AMA-07 | StudioChat no test harness exibe tool calls e respostas do agent durante steps do workflow | P0 |
| AMA-08 | Suporte a múltiplos agent nodes no mesmo workflow com threads isoladas por nó (configurável) | P1 |
| AMA-09 | Documentação de padrão "autonomous agent in workflow" para consumidores do pacote | P0 |
| AMA-10 | Testes integração: imagem + loop + agent tool call + exit por condition | P0 |

## Acceptance Criteria

- Usuário anexa PDF no workflow test harness; agent node no loop lê conteúdo e atualiza state; segunda iteração mantém contexto da thread.
- Workflow com loop + agent completa sem intervenção humana quando condition satisfeita.
- Trace registra steps com referência a thread_id e attachments hash/count.
- Template autonomous-lead-qualification executável após implementação de `workflow-cyclic-graphs`.
- Playground de agent standalone e workflow test harness compartilham mesmo `MessageFactory` e validação de attachments.

## Documentation

| Arquivo | O que adicionar |
|---------|-----------------|
| `guides/workflows/overview.md` | Seção "Agentes autônomos em workflows" — visão do padrão end-to-end |
| `guides/workflows/node-types/ai-nodes.md` | Agent node com attachments e thread em contexto de workflow |
| `guides/agents/attachments.md` | Cross-link: attachments em workflows vs playground |
| `guides/agents/playground-and-threads.md` | Equivalência thread_id workflow ↔ agent playground |
| `guides/workflows/runtime-and-traces.md` | Propagação de attachments e thread entre steps |
| `guides/templates.md` | Template autonomous-lead-qualification |
| `getting-started/quickstart-first-workflow.md` | Tutorial curto multimodal + loop |
| `reference/configuration.md` | `attachments.*`, `chat_history_context_window` em contexto workflow |
