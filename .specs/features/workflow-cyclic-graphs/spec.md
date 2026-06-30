# Grafos Cíclicos em Workflows — Especificação

## Overview

Hoje o Studio valida e executa workflows apenas como DAGs (grafo acíclico dirigido). Isso impede padrões essenciais de agentes autônomos — como reprocessar dados até convergir, pedir esclarecimentos em loop ou iterar extração/validação — sem duplicar nós no canvas.

Esta feature introduz **execução cíclica controlada**: um nó `loop` com guardrail `max_steps`, detecção de ciclos no `GraphValidator` com exceção opcional para back-edges quando o workflow declara limite de iterações, e um template de referência (lead qualification com re-parse). O valor para o usuário é poder modelar fluxos iterativos no editor visual com segurança contra loops infinitos.

## Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| LOOP-01 | Novo tipo de nó `loop` no `NodeTypeRegistry` e canvas com handles `continue` e `exit` | P0 |
| LOOP-02 | Campo `max_steps` configurável no nó `loop` (default global em config) | P0 |
| LOOP-03 | `GraphExecutionLoop` incrementa contador de iteração por ciclo e interrompe com erro claro ao exceder `max_steps` | P0 |
| LOOP-04 | `GraphValidator` detecta ciclos no grafo e rejeita grafos acíclicos-only com back-edges não autorizados | P0 |
| LOOP-05 | Back-edges permitidos quando workflow ou nó `loop` declara `max_steps` > 0 (validação relaxada para subgrafo do loop) | P0 |
| LOOP-06 | Estado `__loop_iterations` (ou por nó) persistido em trace/step para observabilidade | P0 |
| LOOP-07 | Template `lead-qualification-loop` demonstrando re-parse até email válido ou max_steps | P0 |
| LOOP-08 | Export codegen (`LoopNodeCodeGenerator`) gera nó Neuron com lógica de iteração e `max_steps` | P1 |
| LOOP-09 | Inspector no canvas mostra iteração atual durante test harness | P1 |
| LOOP-10 | Testes cobrindo validação de ciclo, max_steps, template e resume após HITL dentro de loop | P0 |

## Acceptance Criteria

- Editor permite conectar aresta de volta a nó anterior quando um nó `loop` com `max_steps` está no caminho; sem `max_steps`, validação falha com mensagem acionável.
- Execução de workflow com loop re-parseia lead até condição `exit` ou estoura `max_steps` com trace `failed` e mensagem identificável.
- `GraphExecutionLoop` não entra em loop infinito — sempre termina por `stop`, `exit` do loop ou `max_steps`.
- Template lead-qualification-loop importável e executável no test harness.
- Export PHP do template gera classes compiláveis com padrão de loop documentado no skill neuron-workflow-architect.

## Documentation

Arquivos em `docs/` a criar ou atualizar na implementação:

| Arquivo | O que adicionar |
|---------|-----------------|
| `guides/workflows/node-types/flow-nodes.md` | Seção do nó `loop`: handles, `max_steps`, exemplos de back-edge |
| `guides/workflows/state-and-conditions.md` | Estado de iteração (`__loop_iterations`), roteamento `continue` vs `exit` |
| `guides/workflows/overview.md` | Parágrafo sobre grafos cíclicos vs DAG-only anterior |
| `guides/workflows/runtime-and-traces.md` | Como iterações aparecem em traces e steps |
| `guides/templates.md` | Template lead-qualification-loop e quando usar loops |
| `reference/configuration.md` | `neuronai-studio.loop.default_max_steps` (se adicionado) |
| `extending/custom-node-types.md` | Padrão para nós com múltiplos handles e guardrails |
