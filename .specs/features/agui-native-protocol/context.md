# AG-UI Native Protocol Context

**Gathered:** 2026-08-13  
**Spec:** `.specs/features/agui-native-protocol/spec.md`  
**Status:** Ready for design  
**Source:** [issue #93](https://github.com/digitalelvis/neuronai-studio/issues/93) + plan discussion.

---

## Feature Boundary

Make Studio AG-UI integrate endpoints speak AG-UI natively: accept `RunAgentInput`, echo `threadId`/`runId`, emit `MESSAGES_SNAPSHOT` + workflow `STATE_SNAPSHOT`/`STATE_DELTA`, and dual-emit canonical HITL interrupt/resume alongside the existing CUSTOM + resume URL. No new `copilotkit` protocol. No playground SSE changes.

---

## Implementation Decisions

### Scope

- Full issue in this feature (P1 wire + P2 snapshots + P2 HITL).
- Line: `v3.1.x`, milestone M17, independent of M14/M15/M16 canvas work.

### HITL (locked)

- **Dual-emit:** keep `CUSTOM` `name=awaiting_input` + `…/traces/{trace}/resume/{protocol}`.
- Also emit `RUN_FINISHED.outcome = { type: "interrupt", interrupts: [...] }`.
- Canonical resume: `POST` the **same** workflow stream URL with `resume[]`.
- `interruptId` = Studio run UUID (= `trace_id` in CUSTOM).

### State events (locked)

- Emit `STATE_SNAPSHOT` and `STATE_DELTA` (RFC 6902) for workflows.
- Agents: empty `STATE_SNAPSHOT` `{}` at start; no deltas.
- Strip keys starting with `__` from client-facing state.

### Input mapping (locked)

- Last `role=user` in `messages[]` → runner `message`.
- `tools[]` ignored.
- `threadId` is a string (not forced UUID).
- `{ message, thread_id }` remains valid fallback for non-CopilotKit hosts.

### Agent's Discretion

- JSON Patch helper in-package (no new Composer dep); top-level + nested object/array diffs.
- How to inject snapshots into agent `handler->events($adapter)` (wrap generator vs adapter subclass).
- Tool-approval resume via `payload.approval` when status is `awaiting_tool_approval`.
- `resume[].status = cancelled` → 422 in v1.

---

## Specific References

- CopilotKit `HttpAgent` POST body: `RunAgentInput` (`threadId`, `runId`, `messages`, `tools`, `state`, `context`, `forwardedProps`).
- AG-UI interrupts: [docs.ag-ui.com/concepts/interrupts](https://docs.ag-ui.com/concepts/interrupts) — snapshots **before** interrupt `RunFinished`; next run on same `threadId` carries `resume[]`.
- neuron-ai `AGUIAdapter` already SCREAMING_SNAKE (`RUN_STARTED`, …) — issue note on neuron-ai #547 is already satisfied.

---

## Deferred Ideas

- Agent tool-approval HITL on integrate stream.
- Dropping CUSTOM / resume URL (breaking; would be a later minor after deprecation).
- Separate `copilotkit` protocol in `StreamAdapterRegistry` available list.
- Frontend-defined AG-UI `tools[]`.
