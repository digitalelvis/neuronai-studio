# Queue Runner para Workflows — Especificação

## Overview

`config/neuronai-studio.php` define `queue` e `queue_connection`, mas não existe job que execute workflows de forma assíncrona. Esta feature introduz `RunWorkflowJob` para disparar runs em background, atualizar trace, e notificar via eventos/polling — habilitando workflows longos (loops, RAG, agent) fora do request HTTP.

## Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| QR-01 | `RunWorkflowJob` implementa `ShouldQueue`, usa `neuronai-studio.queue` config | P0 |
| QR-02 | Dispatch via `WorkflowRunner::dispatch()` ou controller dedicado | P0 |
| QR-03 | Trace criado upfront com status `queued` → `running` → terminal | P0 |
| QR-04 | Falhas gravam `failed` com exception message; retries configuráveis | P0 |
| QR-05 | Resume após HITL também enfileirável (`ResumeWorkflowJob`) | P1 |
| QR-06 | API `POST /workflows/{id}/run` retorna `{ trace_id, status: queued }` | P0 |
| QR-07 | Polling ou SSE separado `GET /traces/{id}/stream` para status | P1 |
| QR-08 | Testes: fake queue, job runs, trace completed | P0 |

## Acceptance Criteria

- `RunWorkflowJob` processado na queue configurada completa trace com sucesso.
- Request HTTP retorna imediatamente com trace_id.
- Job respeita `queue_connection` quando setado.
- Compatível com workflows síncronos no test harness (sem queue).

## Documentation

| Arquivo | O que adicionar |
|---------|-----------------|
| `guides/workflows/runtime-and-traces.md` | Execução assíncrona via queue |
| `guides/export-and-production.md` | Rodar workflows em produção com workers |
| `reference/configuration.md` | `queue`, `queue_connection` |
| `reference/artisan-commands.md` | `queue:work` requirement |
| `getting-started/installation.md` | Nota sobre worker para async workflows |
