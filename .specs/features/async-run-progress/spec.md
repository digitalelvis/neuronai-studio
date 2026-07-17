# Async Run Progress Specification

## Problem Statement

Async workflow jobs (`RunWorkflowJob` / resume) run with `emitter: null`. Clients only poll `GET .../traces/{id}/json` and see coarse status; node steps often appear only at finalize. Sync harness already has rich SSE — async should reach parity without requiring Laravel Echo.

## Goals

- [ ] Jobs emit progress into a durable **progress buffer** (cache) keyed by `run_id`.
- [ ] Studio SSE endpoint tails the buffer until terminal status.
- [ ] Incremental flush of spans/`__steps` so polling fallback improves.
- [ ] Config for TTL / enable; docs.

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Laravel Echo / `ShouldBroadcast` as primary | Deferred (AD-019) |
| Replacing sync harness SSE | Keep existing POST stream |
| Guaranteed exactly-once delivery | Best-effort ordered append |

**Context:** [m6-runtime-agent/context.md](../m6-runtime-agent/context.md)  
**Depends on:** queue runner (jobs exist)

---

## User Stories

### P1: ProgressEmitter on jobs ⭐ MVP

**User Story**: As the runtime, I want async jobs to record the same step events the sync emitter would fire so that a listener can stream them.

**Acceptance Criteria**:

1. WHEN `RunWorkflowJob` / resume job handles a run THEN it SHALL pass a `ProgressEmitter` (not null) into `WorkflowRunner`.
2. WHEN a step event fires THEN emitter SHALL append `{seq, event, data, at}` to cache list `neuronai-studio:run-progress:{run_id}` with configured TTL.
3. WHEN a node completes THEN system SHALL flush incremental span/`__steps` persistence (best-effort) so JSON poll shows progress before finalize.
4. WHEN run reaches terminal status THEN emitter SHALL append a terminal marker event (e.g. `run_completed` / `run_failed` / reuse existing `trace_completed`) and stop accepting writes after grace.

**Independent Test**: Fake cache → run job with mocked runner emitting steps → assert buffer length and seq order.

---

### P1: SSE tail endpoint ⭐ MVP

**User Story**: As a Studio UI client, I want to open an EventSource on an async run and receive live events until completion.

**Acceptance Criteria**:

1. WHEN `GET /studio/workflows/runs/{run}/events/stream` (auth Studio) is called THEN system SHALL SSE-tail the progress buffer from `seq=0` or `Last-Event-ID` / `?after=`.
2. WHEN new events appear THEN they SHALL be flushed as SSE `event:` / `data:` matching sync harness shapes where applicable.
3. WHEN run is already terminal and buffer empty/expired THEN endpoint MAY synthesize final status from DB and close.
4. WHEN client disconnects THEN server SHALL stop the tail loop without failing the job.

**Independent Test**: Seed buffer + run status running → stream receives events → mark completed → stream ends.

---

### P2: Config + docs

**Acceptance Criteria**:

1. Config keys under `neuronai-studio.async_progress` (`enabled`, `ttl`, `poll_ms` for SSE wait).
2. Docs in runtime-and-traces + configuration; note Echo deferred.

---

## Edge Cases

- WHEN cache driver is `array` in tests THEN buffer still works within process.
- WHEN TTL expires mid-run THEN SSE falls back to DB status; document TTL guidance (default ≥ job timeout).
- WHEN two SSE clients connect THEN both MAY tail independently (fan-out from buffer).

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| ARP-01 | P1: ProgressEmitter | Tasks | Pending |
| ARP-02 | P1: SSE tail | Tasks | Pending |
| ARP-03 | P2: Config + docs | Tasks | Pending |

---

## Success Criteria

- [ ] Async run in Studio can subscribe to live events without Echo.
- [ ] Polling JSON shows steps before job end.
- [ ] Feature disableable via config (jobs use null emitter when disabled).
