# Unified Threads, Runs, and Traces Specification

## Problem Statement

Atualmente, o NeuronAI Studio trata a execução de Workflows e Agents de forma assimétrica. Workflows utilizam a tabela `workflow_traces` atuando como a máquina de estado e log, enquanto Agents não possuem persistência de estado para "Runs", dificultando a retomada em pausas (como Tool Approval) num ambiente distribuído. Adicionalmente, há mistura semântica: retomamos "traces" (conceito de observabilidade) em vez de "runs" (conceito de execução), e não temos rastreabilidade granular do consumo de tokens (token tracking) em spans de execução de agentes/LLMs.

## Goals

- [x] Unificar as nomenclaturas de execução para Workflows e Agents (Threads, Runs, Traces, Spans).
- [x] Centralizar o estado de execução (Runs) numa tabela única (`neuronai_studio_runs`) permitindo pausas/retomadas seguras para ambos.
- [x] Implementar rastreamento granular do consumo de tokens (prompt, completion, total) para fins de debug e custeio no Neuron Debugger.
- [x] Limpar as tabelas/migrations legadas não publicadas e ajustar as rotas públicas de integração.

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
| ----------- | -------------- |
| Global State na Thread | Deixaremos a coluna pronta no banco, mas a implementação lógica entrará num milestone futuro do roadmap. |
| Dashboard analítico de Custos | O foco atual é gravar e expor no Neuron Debugger (Trace Detail), não criar painéis BI/faturamento. |
| Refatoração do Frontend do Studio | Faremos apenas os ajustes de exibição (badges de token) e rotas (URLs de resume); não reescreveremos componentes inteiros do Studio React. |

---

## User Stories

### P1: Unificação de Tabelas de Execução ⭐ MVP

**User Story**: As a Developer, I want Workflows and Agents to share the same database structure for Threads and Runs so that I have a single point of truth for managing executions and state.

**Why P1**: Foundation block. Without this, we can't persist Agent tool approvals cleanly nor track tokens uniformly.

**Acceptance Criteria**:

1. WHEN a Workflow or Agent starts THEN system SHALL create or reuse a `Thread` and create a new `Run`.
2. WHEN a Workflow pauses at a Human node THEN system SHALL save its checkpoint state to the `Run` record.
3. WHEN an Agent pauses for Tool Approval THEN system SHALL save its interrupt state to the `Run` record instead of serializing it to the client.

**Independent Test**: Can run an agent, pause for tool approval, restart the server, and successfully resume the agent run using the stored DB state.

---

### P1: Token Tracking ⭐ MVP

**User Story**: As a Developer, I want to see token consumption for every LLM/Agent step so that I can debug inference costs per interaction.

**Why P1**: Essential for production deployment planning and optimization.

**Acceptance Criteria**:

1. WHEN an LLM provider returns usage data THEN system SHALL record `prompt_tokens` and `completion_tokens` on the `TraceSpan`.
2. WHEN a Run completes THEN system SHALL aggregate all span tokens and store the total in the `Run` record.

**Independent Test**: Run a workflow with an LLM node and verify the database has token counts in the `neuronai_studio_runs` and `neuronai_studio_trace_spans` tables.

---

### P1: API de Integração Unificada ⭐ MVP

**User Story**: As an API Consumer, I want a single endpoint pattern to resume paused Workflows or Agents so that my integration code is clean and semantically correct.

**Why P1**: We need to fix the `/traces/{id}/resume` route to `/threads/{t}/runs/{r}/resume` before the package goes GA.

**Acceptance Criteria**:

1. WHEN hitting `POST /api/neuronai/threads/{t}/runs/{r}/resume/{protocol}` THEN system SHALL successfully resume the specified run.

**Independent Test**: Can resume a workflow via external API using the new route structure.

---

## Edge Cases

- WHEN an old client tries to use the old `/traces` resume route THEN system SHALL return 404 (since we decided against retrocompatibility).
- WHEN an LLM provider does not return token usage THEN system SHALL default token counts to 0 without failing the span creation.
- WHEN a workflow branch pauses in parallel execution THEN system SHALL correctly save the checkpoint mapped to the shared Run state.

---

## Requirement Traceability

Each requirement gets a unique ID for tracking across design, tasks, and validation.

| Requirement ID | Story | Phase | Status |
| -------------- | ----------- | ------ | ------- |
| CORE-01 | P1: Unificação de Tabelas | Design | Done |
| CORE-02 | P1: Token Tracking | Design | Done |
| CORE-03 | P1: API Unificada | Design | Done |

**Coverage:** 3 total, 3 mapped to tasks, 0 unmapped

---

## Success Criteria

How we know the feature is successful:

- [x] Agent Runner successfully pauses and resumes using DB Run state (no `InMemoryPersistence`).
- [x] Neuron Debugger JSON payload includes `prompt_tokens`, `completion_tokens`, `total_tokens`.
- [x] No references to `workflow_traces` as a "Run" remain in the codebase.
