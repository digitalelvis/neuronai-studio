# Token Streaming em Workflows — Tasks

**Design**: `.specs/features/workflow-token-streaming/design.md`
**Spec**: `.specs/features/workflow-token-streaming/spec.md`
**Status**: In progress — M2 feature 6 (slice 1: backend token SSE entregue)

> **TESTING.md**: inexistente neste repositório. Matriz inferida dos padrões em `tests/`:
>
> | Camada | Tipo de teste | Comando gate | Parallel-Safe |
> |--------|---------------|--------------|---------------|
> | `AgentRunner` | unit/integration | `vendor/bin/phpunit --filter {Class}Test` | Não (DB para AgentDefinition) |
> | `*NodeExecutor` | unit/integration | `vendor/bin/phpunit --filter {Class}Test` | Não (DB) |
> | `WorkflowRunner` / controllers | integration | `vendor/bin/phpunit --filter {Class}Test` | Não (DB) |
> | studio-chat JS | none (handler `token` já existe) | — | N/A |
> | `docs/` | none | revisão manual | Sim |

---

## Slicing

| Slice | Escopo | Requisitos |
|-------|--------|------------|
| **1 — Backend token SSE** | `AgentRunner::streamInline`, streaming em `AgentNodeExecutor` + `LlmNodeExecutor`, SSE `token` via `emitStep`, testes | TS-01, TS-02, TS-03, TS-04, TS-06, TS-08 |
| 2 — Toggle + docs polish | inspector toggle stream (canvas), default on no harness, docs frontend-bundles | TS-07, docs restantes |

`WorkflowStreamController` e o `WorkflowSessionAdapter`/`StudioChat` já propagam eventos SSE arbitrários e agregam `token` na bolha assistant (TS-04/TS-05 sem mudança de código).

---

## Execution Plan

### Phase 1: Runner streaming (Sequential)

```
T1 → T2
```

### Phase 2: Node executors (Parallel OK após T1)

```
T1 ──┬→ T2 [agent]
     └→ T3 [llm]
```

### Phase 3: Tests + docs (Sequential)

```
T2 + T3 ──→ T4 → T5
```

---

## Task Breakdown

### T1: `AgentRunner::streamInline`

**What**: Generator inline que faz stream do agente, emite chunks e retorna `AgentRunResult` (conteúdo + tool events) via `return` do generator.
**Where**: `src/Runtime/AgentRunner.php`
**Depends on**: None
**Reuses**: `makeAgent`, `stream()` (playground), `ToolEventExtractor::fromChatHistory`, `AgentRunResult`
**Requirement**: TS-01

**Done when**:

- [x] `streamInline(array $config, string|UserMessage $message, ?AgentDefinition, ?string $threadKey, bool $fake): Generator` yield `StreamChunk`
- [x] `return` do generator = `AgentRunResult($content, $toolEvents)` após consumir os eventos
- [x] Sem alterar `runInline`/`stream` existentes

**Tests**: coberto em T4
**Gate**: quick

**Commit**: `feat(runtime): add streamInline generator to AgentRunner`

---

### T2: Streaming no `AgentNodeExecutor`

**What**: Quando `data.stream === true` (e não structured, não tool-approval) e o state tem emitter, consumir `streamInline` e emitir SSE `token` `{node_id, delta}` por chunk; persistir conteúdo final + tool events.
**Where**: `src/Runtime/NodeExecutors/AgentNodeExecutor.php`
**Depends on**: T1
**Reuses**: `BuilderWorkflowState::emitStep`, `emitToolEvents`
**Requirement**: TS-03, TS-06

**Done when**:

- [x] Branch stream só ativa com `BuilderWorkflowState` + `stepEmitter` presente
- [x] Fallback blocking (`runInline`) quando stream off, structured, ou tool-approval habilitado (sem regressão)
- [x] `token` emitido por `TextChunk` não vazio; `output_key` + tool events preservados
- [x] Gate: `vendor/bin/phpunit --filter WorkflowTokenStreamingTest`

**Tests**: T4
**Gate**: quick

**Commit**: `feat(runtime): stream agent node tokens in workflow harness`

---

### T3: Streaming no `LlmNodeExecutor`

**What**: Quando `data.stream === true` (não structured) e state com emitter, usar `provider->stream()` e emitir `token`.
**Where**: `src/Runtime/NodeExecutors/LlmNodeExecutor.php`
**Depends on**: T1 (padrão), independente em código
**Reuses**: `ProviderRegistry::resolve`, `BuilderWorkflowState::emitStep`
**Requirement**: TS-02, TS-03, TS-06

**Done when**:

- [x] Stream via `AIProviderInterface::stream()`; `getReturn()` = mensagem final → `output_key`
- [x] Fallback blocking `chat()` quando stream off ou structured (sem regressão)
- [x] Gate: `vendor/bin/phpunit --filter WorkflowTokenStreamingTest`

**Tests**: T4
**Gate**: quick

**Commit**: `feat(runtime): stream llm node tokens in workflow harness`

---

### T4: Testes de token streaming

**What**: Cobrir emissão de tokens + agregação de conteúdo + regressão blocking.
**Where**: `tests/WorkflowTokenStreamingTest.php`
**Depends on**: T2, T3
**Reuses**: `FakeAIProvider` (`stream()` chunked), `BuilderWorkflowState` com `stepEmitter`
**Requirement**: TS-08

**Done when**:

- [x] Agent node: N eventos `token` cuja concatenação == conteúdo; `output_key` setado
- [x] Llm node: idem via provider stream
- [x] Regressão: sem `stream` → nenhum `token`, usa `chat` blocking
- [x] Tool-approval + stream → fallback blocking (sem token, pausa preservada)
- [x] Gate: `vendor/bin/phpunit --filter WorkflowTokenStreamingTest`

**Tests**: unit/integration
**Gate**: full

**Commit**: `test(runtime): cover workflow token streaming`

---

### T5: Documentação

**What**: Documentar eventos `token` no SSE e a opção `stream` nos nós AI.
**Where**: `docs/guides/workflows/runtime-and-traces.md`, `docs/guides/workflows/node-types/ai-nodes.md`
**Depends on**: T4
**Reuses**: Estilo docs SSE existente
**Requirement**: spec Documentation table

**Done when**:

- [x] Seção `token` no fluxo SSE (ordem `step_started → token* → step_completed`)
- [x] Opção `stream` em LLM/Agent node documentada
- [ ] `playground-and-threads.md` / `frontend-bundles.md` — slice 2

**Tests**: none
**Gate**: manual review

**Commit**: `docs(workflows): document workflow token streaming`

---

## Traceability

| Requirement | Tasks |
|-------------|-------|
| TS-01 | T1 |
| TS-02 | T3 |
| TS-03 | T2, T3 |
| TS-04 | (existente — WorkflowStreamController) |
| TS-05 | (existente — StudioChat/WorkflowSessionAdapter) |
| TS-06 | T2, T3 |
| TS-07 | slice 2 |
| TS-08 | T4 |

---

## Optional (defer — slice 2)

| Item | Motivo |
|------|--------|
| Toggle `stream` no inspector canvas (LLM/Agent) | Frontend polish; P1 |
| Default stream on no harness | Requer toggle canvas |
| `reference/frontend-bundles.md` token handling | Docs polish |
| Streaming + tool approval no mesmo nó | Interrupt serialization com stream é complexo; blocking cobre HITL |
