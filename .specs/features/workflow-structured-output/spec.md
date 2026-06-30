# Structured Output em Workflows — Especificação

## Overview

Nós LLM e Agent hoje gravam resposta como string em `output_key`. Esta feature adiciona **modo structured**: classes de saída tipadas (SchemaProperty), validação NeuronAI, e roteamento por condition nodes em campos do state tipado — habilitando workflows que extraem JSON confiável e ramificam por valores concretos.

## Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| SO-01 | Toggle `structured: true` em nós `llm` e `agent` no inspector | P0 |
| SO-02 | Seletor de output class (scan `export_path` ou registry Studio) | P0 |
| SO-03 | `LlmNodeExecutor` / `AgentNodeExecutor` chamam `structured()` / `StructuredOutput` Neuron | P0 |
| SO-04 | State armazena objeto/array validado em `output_key`, não apenas string | P0 |
| SO-05 | Condition node suporta `state_key` com dot notation (`lead.email`) | P0 |
| SO-06 | Erro de validação structured → trace step failed com detalhes | P0 |
| SO-07 | Codegen exporta output class ou import existente | P1 |
| SO-08 | UI preview do schema no inspector | P1 |
| SO-09 | Testes round-trip: LLM fake → structured object → condition branch | P0 |

## Acceptance Criteria

- Workflow extrai objeto tipado e condition roteia por campo nested.
- Invalid structured response marca step como failed com mensagem de validação.
- Export PHP inclui output class quando definida inline no Studio.
- Compatível com workflows existentes (structured off = comportamento atual).

## Documentation

| Arquivo | O que adicionar |
|---------|-----------------|
| `guides/workflows/node-types/ai-nodes.md` | Structured mode em LLM e Agent |
| `guides/workflows/state-and-conditions.md` | Dot notation e tipos no state |
| `guides/agents/creating-agents.md` | Output classes compartilhadas agent/workflow |
| `reference/configuration.md` | `structured_output_scan_paths` |
| `extending/custom-node-types.md` | Integrar StructuredOutput em executor custom |
