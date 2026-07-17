# M6 Runtime / Agent — Context

**Gathered:** 2026-07-16  
**Milestone:** M6 — Runtime / Agent (desempenho e flexibilidade)  
**Status:** Specify → Design → Tasks → Execute on `v0.7.x` (AD-019)  
**Tasks index:** [tasks.md](./tasks.md)  
**Specs:** [`agent-tool-controls`](../agent-tool-controls/spec.md) · [`async-run-progress`](../async-run-progress/spec.md) · [`interpreted-parallel-concurrency`](../interpreted-parallel-concurrency/spec.md)

---

## Feature Boundary

M6 entrega **controles e observabilidade do tool-loop**, **progresso live em runs async** (sem Echo), e **fork/join concorrente** no runtime interpretado. Não entrega billing/Usage avançado, tool approval em branches, nem `ShouldBroadcast` como transporte primário.

---

## Implementation Decisions

### Escopo do milestone (AD-019)

- **P1 no mesmo milstone, ordem Execute:** `agent-tool-controls` → `async-run-progress` → `interpreted-parallel-concurrency`.
- Critério de conclusão: knobs + tools mid-stream; SSE de progresso em job; fork I/O-bound mais rápido que sequencial com resume parcial.

### Premissa corrigida — multi-turn

- Neuron Agent já faz multi-round tools (`toolMaxRuns` default 10) dentro de um `chat()`/`stream()` por visita ao nó.
- Autonomia cross-iteration via loop + thread compartilhada já existe (AMA).
- Gap Studio: **expor knobs**, **emitir tool SSE mid-loop**, não reinventar o loop.

### Async progress (travado)

- **Buffer de progresso** (cache/Redis, chave por `run_id`, TTL configurável) + **SSE tail** endpoint Studio.
- Jobs usam `ProgressEmitter` (grava buffer + flush incremental de spans/`__steps`).
- Polling JSON permanece fallback.
- `ShouldBroadcast` / Echo = **deferred**.

### Parallel concurrency (travado)

- Concorrência via **Amp** (transitivo `amphp/amp` via neuron-ai) no path interpretado.
- Config `parallel.concurrency` = `sequential|concurrent` (default `concurrent` quando Amp ok).
- Fallback sequencial se Amp indisponível ou config = `sequential`.
- Tool approval em branch = **fora** (AD-007 mantido).

### Agent's Discretion (resolved in Design)

- Schema: colunas/casts em `AgentDefinition` + override em `data` do nó agent.
- Live tool SSE: mapear chunks de tool durante `streamInline`; blocking path pode emitir via callback/history incremental se Neuron expor — prefer stream path + post-history dedupe.
- Progress buffer store: Laravel Cache (Redis preferred; array/file ok em tests).
- SSE path: `GET /studio/workflows/runs/{run}/events/stream` (auth Studio).

---

## Deferred Ideas (explicit)

- Laravel Echo / `ShouldBroadcast` para progresso async.
- Tool approval dentro de parallel branches.
- PE-08 join inspector preview; SO T12 loop hint; RAG hybrid/MMR.
- Página Usage / multi-tenant / billing providers.
