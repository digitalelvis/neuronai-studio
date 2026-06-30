# Execução Paralela em Workflows — Especificação

## Overview

Workflows Studio executam nós sequencialmente. Para cargas que beneficiam concorrência (ex.: extrair dados estruturados e gerar descrição de imagem em paralelo), esta feature adiciona nós **fork** e **join** mapeados ao padrão `ParallelEvent` do NeuronAI, com `AsyncExecutor`, resume via `BranchInterrupt`, e codegen correspondente.

## Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| PE-01 | Nós `fork` e `join` no canvas com branches nomeadas | P0 |
| PE-02 | `ForkNodeExecutor` dispara branches via runtime paralelo interno | P0 |
| PE-03 | `JoinNodeExecutor` agrega resultados em `output_key` | P0 |
| PE-04 | `GraphValidator` valida pareamento fork→join e branches completas | P0 |
| PE-05 | Interrupção em branch → `BranchInterrupt` com contexto parallel | P0 |
| PE-06 | Resume retoma apenas branch interrompida (Neuron semantics) | P0 |
| PE-07 | `ForkNodeCodeGenerator` / `JoinNodeCodeGenerator` emitem `ParallelEvent` subclass | P0 |
| PE-08 | Inspector fork: lista branches, join: preview resultados agregados | P1 |
| PE-09 | Testes: 2 branches fake, join merge, interrupt + resume partial | P0 |

## Acceptance Criteria

- Workflow fork com 2 branches LLM converge no join com objeto `{ branch_a: ..., branch_b: ... }`.
- Human node em uma branch pausa workflow; resume completa só branch pendente.
- Export PHP compila com `ParallelEvent` custom subclass.
- Grafo inválido (fork sem join) rejeitado na validação.

## Documentation

| Arquivo | O que adicionar |
|---------|-----------------|
| `guides/workflows/node-types/logic-nodes.md` | Fork e Join |
| `guides/workflows/overview.md` | Quando usar paralelo vs sequencial |
| `guides/workflows/runtime-and-traces.md` | Branch steps, parallel interrupt |
| `guides/workflows/human-in-the-loop.md` | HITL em branches paralelas |
| `extending/custom-node-types.md` | Padrão fork/join |
