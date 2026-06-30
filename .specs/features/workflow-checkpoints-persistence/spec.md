# Checkpoints e Persistência em Workflows — Especificação

## Overview

Hoje apenas o nó Human persiste checkpoint via trace para resume. Esta feature generaliza **checkpoints por nó** além de HITL, usando `EloquentPersistence` (ou adapter) para pular re-execução de operações caras ao retomar — alinhado ao `$this->checkpoint()` do Neuron.

## Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| CP-01 | `CheckpointService` registra resultado por `trace_id + node_id + iteration` | P0 |
| CP-02 | Executors opt-in via flag `checkpoint: true` no nó (rag, llm, agent, tool) | P0 |
| CP-03 | Resume (human, tool approval, queue) skip re-execução se checkpoint válido | P0 |
| CP-04 | `EloquentPersistence` adapter para Neuron native workflows | P1 |
| CP-05 | Tabela `workflow_checkpoints` com TTL/config purge | P0 |
| CP-06 | Invalidação de checkpoint quando input state relevante muda | P1 |
| CP-07 | UI trace inspector: badge "cached" em steps com checkpoint hit | P1 |
| CP-08 | Testes: primeira run grava checkpoint; resume não chama provider fake 2x | P0 |

## Acceptance Criteria

- Resume após Human node não re-executa nó RAG anterior com checkpoint on.
- Checkpoint armazenado em DB e associado ao trace.
- Config global permite desabilitar checkpoints (`neuronai-studio.checkpoints.enabled`).
- Loops: checkpoint scoped por iteration index.

## Documentation

| Arquivo | O que adicionar |
|---------|-----------------|
| `guides/workflows/runtime-and-traces.md` | Checkpoints e cache de nós |
| `guides/workflows/human-in-the-loop.md` | Resume sem re-run |
| `reference/database-schema.md` | `workflow_checkpoints` |
| `reference/configuration.md` | `checkpoints.*` |
| `extending/custom-node-types.md` | Opt-in checkpoint em executor |
