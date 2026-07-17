# Interpreted Parallel Concurrency Specification

## Problem Statement

Fork/join in the Studio interpreted runtime runs branches **sequentially** (AD-007) despite isolated state clones and native codegen emitting `ParallelEvent`. Multi-LLM forks pay full serial latency. Amp is already available transitively.

## Goals

- [ ] Run interpreted fork branches concurrently via Amp when enabled.
- [ ] Preserve state isolation, join merge, Human interrupt resume (reuse completed branches).
- [ ] Serialize SSE/`branch_*` emissions safely.
- [ ] Config fallback to sequential; tests for race + resume.

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Tool approval inside a branch | AD-007 / deferred |
| Changing native export ParallelEvent | Already concurrent in Neuron |
| PE-08 join inspector preview | Deferred polish |

**Context:** [m6-runtime-agent/context.md](../m6-runtime-agent/context.md)  
**Depends on:** `workflow-parallel-execution`; benefits from `async-run-progress` emitter serialization patterns

---

## User Stories

### P1: Concurrent branch execution ⭐ MVP

**User Story**: As a workflow author, I want fork branches with independent I/O to run at the same time so that wall-clock latency drops.

**Acceptance Criteria**:

1. WHEN `parallel.concurrency=concurrent` and Amp is available THEN `ForkNodeExecutor` SHALL run pending branches concurrently (isolated state clones).
2. WHEN join is reached THEN outputs SHALL merge by branch id with the same semantics as sequential mode.
3. WHEN `parallel.concurrency=sequential` OR Amp missing THEN behavior SHALL match today’s sequential foreach.
4. WHEN a branch emits steps THEN events SHALL include `branch_id` and not corrupt other branches’ emissions (serialized emitter).

**Independent Test**: Two branches each sleep/mock-delay 100ms → concurrent wall time ≪ 200ms; sequential ≥ ~200ms.

---

### P1: Resume mid-fork unchanged ⭐ MVP

**User Story**: As an operator, I want Human interrupt inside a branch to resume without re-running completed branches.

**Acceptance Criteria**:

1. WHEN resume loads parallel checkpoint THEN completed branches SHALL be skipped, pending resumed, not-started run (concurrently if mode allows).
2. WHEN tool approval occurs inside a branch THEN system MAY fail closed / document unsupported (no new support required).

**Independent Test**: Existing parallel resume tests pass under concurrent mode.

---

### P2: Config + docs

**Acceptance Criteria**:

1. Config `neuronai-studio.parallel.concurrency` = `sequential|concurrent` (default `concurrent`).
2. Docs: logic-nodes, runtime-and-traces, configuration; note tool-approval-in-branch still unsupported.

---

## Edge Cases

- WHEN one branch throws THEN other in-flight branches SHALL be cancelled/awaited safely and run marked failed (or parallel_interrupt if Human) — design picks deterministic fail-fast.
- WHEN emitter is null (legacy) THEN concurrency still works without SSE.
- WHEN single branch fork THEN no Amp overhead required (may run inline).

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| IPC-01 | P1: Concurrent branches | Tasks | Pending |
| IPC-02 | P1: Resume mid-fork | Tasks | Pending |
| IPC-03 | P2: Config + docs | Tasks | Pending |

---

## Success Criteria

- [ ] Concurrent mode measurably faster on dual delayed branches.
- [ ] Sequential mode bit-compatible with pre-M6 tests.
- [ ] Resume tests green under both modes.
