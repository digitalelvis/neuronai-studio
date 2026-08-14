# AG-UI Native Protocol Specification

## Problem Statement

Host apps (e.g. 99multas) connect CopilotKit v2 via `HttpAgent` + `selfManagedAgents` to Studio integrate AG-UI streams. Studio M4 documents `{ message }` + `thread_id` / `context`. CopilotKit sends AG-UI `RunAgentInput` (`threadId`, `runId`, `messages[]`). The host currently translates this. The package should speak the protocol natively so the host gateway can shrink. HITL today uses a Studio-specific `CUSTOM` `awaiting_input` + resume URL; CopilotKit expects canonical interrupt/resume on the same stream URL.

**Issue:** [digitalelvis/neuronai-studio#93](https://github.com/digitalelvis/neuronai-studio/issues/93)

## Goals

- [ ] `POST …/stream/agui` accepts AG-UI `RunAgentInput` and still accepts `{ message }` as fallback.
- [ ] `RUN_STARTED` / `RUN_FINISHED` echo client `threadId` and `runId`.
- [ ] Clients hydrate history via `MESSAGES_SNAPSHOT` without a host preload.
- [ ] Workflow clients hydrate and follow state via `STATE_SNAPSHOT` / `STATE_DELTA`.
- [ ] CopilotKit HITL works via canonical `RUN_FINISHED.outcome.interrupt` + `resume[]` on the same stream URL, while the existing CUSTOM + resume URL keep working (dual-emit).

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Separate `copilotkit` protocol route | CopilotKit speaks AG-UI via `HttpAgent`; catalog `copilotkit` stays roadmap-only |
| Frontend-defined `tools[]` in `RunAgentInput` | Server tools already stream as `TOOL_CALL_*` |
| Playground / harness SSE (`token`, `tool_*`) | SA-08: internal wire stays separate |
| Agent tool-approval HITL on integrate stream | Issue is CopilotKit + workflow Human/HITL |
| Upstream neuron-ai `AGUIAdapter` snapshots/outcome | Studio already emits CUSTOM out-of-band; keep that pattern |
| Dropping `…/traces/{trace}/resume/agui` | Dual-emit: old URL stays |

**Context:** [context.md](./context.md)  
**Design:** [design.md](./design.md)  
**Tasks:** [tasks.md](./tasks.md)

---

## User Stories

### P1: Accept RunAgentInput ⭐ MVP

**User Story**: As a host integrating CopilotKit `HttpAgent`, I want to POST AG-UI `RunAgentInput` to Studio agent and workflow AG-UI streams so I do not translate the body.

**Why P1**: Without the wire contract, CopilotKit cannot talk to Studio without a host gateway.

**Acceptance Criteria**:

1. WHEN `POST …/agents/{agent}/stream/agui` or `…/workflows/{workflow}/stream/agui` receives a body with `messages` (array) and/or `threadId`/`runId` THEN the system SHALL treat it as `RunAgentInput` and SHALL NOT require a top-level `message` string.
2. WHEN the body is `{ message, thread_id? }` without `messages`/`threadId`/`runId` THEN the system SHALL keep the M4 fallback and run as today.
3. WHEN `messages[]` is present THEN the runner `message` SHALL be the content of the last item with `role` `user` (string content, or concatenated text parts).
4. WHEN `tools[]` is present THEN the system SHALL ignore it (no frontend tool execution).
5. WHEN protocol is `vercel` THEN request validation SHALL remain the M4 `{ message, thread_id UUID }` contract (no RunAgentInput).

**Independent Test**: POST agent `/stream/agui` with CopilotKit-shaped JSON (no `message` key) → 200 SSE with `RUN_STARTED`. Same URL with `{ message: "hi" }` still works.

---

### P1: Echo threadId and runId ⭐ MVP

**User Story**: As a CopilotKit client, I want `RUN_STARTED` and `RUN_FINISHED` to echo the `threadId` and `runId` I sent so the UI can correlate the run.

**Why P1**: Mismatched IDs break client session state.

**Acceptance Criteria**:

1. WHEN the AG-UI body includes `threadId` (or fallback `thread_id`) THEN `RUN_STARTED.threadId` and `RUN_FINISHED.threadId` SHALL equal that value.
2. WHEN the body includes `runId` THEN `RUN_STARTED.runId` and `RUN_FINISHED.runId` SHALL equal that value.
3. WHEN `runId` is omitted THEN the adapter SHALL generate one and echo it on both lifecycle events.
4. WHEN `threadId` is omitted THEN the system SHALL generate a thread id (not required to be UUID).
5. WHEN AG-UI `threadId` is a non-UUID string THEN the request SHALL succeed (AG-UI does not require UUID).

**Independent Test**: POST with `threadId: "t-abc"` and `runId: "r-1"` → stream contains those strings on `RUN_STARTED` and `RUN_FINISHED`.

---

### P1: Playground isolation

**User Story**: As a Studio operator, I want the internal playground SSE unchanged so integrate work cannot break the harness.

**Why P1**: SA-08 constraint; regression is a ship blocker.

**Acceptance Criteria**:

1. WHEN the playground agent/workflow chat streams THEN the wire SHALL remain Studio events (`token`, `tool_*`, …), not AG-UI.
2. WHEN this feature ships THEN `AgentChatStreamController` / `WorkflowStreamController` SHALL not accept `RunAgentInput`.

**Independent Test**: Existing playground/integrate vercel tests still pass.

---

### P2: MESSAGES_SNAPSHOT

**User Story**: As a CopilotKit client, I want a `MESSAGES_SNAPSHOT` at run start (and before interrupt) so I can hydrate thread history without a host preload API.

**Why P2**: Completes native protocol; P1 already unblocks a translating host.

**Acceptance Criteria**:

1. WHEN an AG-UI agent or workflow stream starts THEN after `RUN_STARTED` the system SHALL emit `MESSAGES_SNAPSHOT` with `{ id, role, content }` from Studio chat history for that public `threadId` (`ChatThreadLoader`).
2. WHEN the thread is new/empty THEN `messages` SHALL be `[]` (or only prior turns if any).
3. WHEN a workflow pauses for HITL THEN the system SHALL emit `MESSAGES_SNAPSHOT` again before `RUN_FINISHED` interrupt.

**Independent Test**: Two-turn agent AG-UI stream on the same `threadId` → second run snapshot includes the first assistant reply.

---

### P2: STATE_SNAPSHOT and STATE_DELTA

**User Story**: As a CopilotKit workflow client, I want workflow state snapshots and JSON Patch deltas so the UI can render shared state without polling traces.

**Why P2**: Issue optional made in-scope for this feature.

**Acceptance Criteria**:

1. WHEN an AG-UI workflow stream starts THEN after `MESSAGES_SNAPSHOT` the system SHALL emit `STATE_SNAPSHOT` with client-facing state (keys starting with `__` stripped).
2. WHEN a workflow step completes with a state change THEN the system SHALL emit `STATE_DELTA` as RFC 6902 ops (`add`/`replace`/`remove`). Empty patch SHALL NOT be emitted.
3. WHEN a workflow pauses for HITL THEN the system SHALL emit `STATE_SNAPSHOT` before the interrupt `RUN_FINISHED`.
4. WHEN the stream is an **agent** (not workflow) THEN `STATE_SNAPSHOT` SHALL be `{}` once at start and SHALL NOT emit `STATE_DELTA`.

**Independent Test**: Human workflow AG-UI stream → `STATE_SNAPSHOT` present; after resume a later `set_state` produces `STATE_DELTA` or a post-interrupt snapshot reflecting `confirmed`.

---

### P2: Canonical HITL (dual-emit)

**User Story**: As a CopilotKit client, I want workflow Human pauses as AG-UI interrupts and to resume on the same stream URL with `resume[]`, without a host-specific resume path. Existing CUSTOM + resume URL clients must keep working.

**Why P2**: Unblocks CopilotKit HITL; dual-emit avoids breaking M4 hosts.

**Acceptance Criteria**:

1. WHEN a workflow AG-UI stream pauses (`awaiting_input` or `awaiting_tool_approval`) THEN the system SHALL emit `CUSTOM` `awaiting_input` with `trace_id` (M4) **and** `RUN_FINISHED` with `outcome: { type: "interrupt", interrupts: [{ interruptId, reason, payload }] }` instead of a vanilla success `RUN_FINISHED`.
2. WHEN `interruptId` is emitted THEN it SHALL equal the Studio run UUID (same as `trace_id`).
3. WHEN `POST …/workflows/{workflow}/stream/agui` includes `resume: [{ interruptId, status: "resolved", payload }]` THEN the system SHALL load that run, resume it, and continue the AG-UI stream.
4. WHEN `payload` is a string THEN it SHALL be the Human message; WHEN it is an object THEN `message` / `input` / `text` SHALL be used; WHEN awaiting tool approval THEN `payload.approval` `approve|reject` SHALL be passed through.
5. WHEN `POST …/workflows/traces/{trace}/resume/agui` with `{ message }` THEN the M4 resume path SHALL still complete the run.
6. WHEN resume `status` is `cancelled` THEN the system SHALL return 422 (v1 does not cancel runs).

**Independent Test**: Pause Human via AG-UI → CUSTOM + interrupt outcome in stream → POST same stream URL with `resume[]` → run `completed`. Repeat with old resume URL.

---

### P2: Docs and Connect Panel

**User Story**: As a host developer, I want Connect Panel and AG-UI docs to show `HttpAgent` / `RunAgentInput` and both resume paths.

**Why P2**: Discovery; otherwise hosts keep translating.

**Acceptance Criteria**:

1. WHEN docs update THEN `guides/integration/ag-ui.md` SHALL document `RunAgentInput`, echoed IDs, snapshots, dual-emit HITL, and both resume mechanisms.
2. WHEN Connect Panel protocol is `agui` THEN the snippet SHALL POST `RunAgentInput`-shaped JSON (not only `{ message }`) and mention CopilotKit `HttpAgent`.

**Independent Test**: Read docs + Connect Panel source; vercel snippet unchanged.

---

## Edge Cases

- WHEN `messages[]` has no user role THEN fallback `message` SHALL be used; if both empty and not a `resume` THEN 422 (agent) or empty workflow message as today.
- WHEN `state` is a non-object (AG-UI may send `{}`) THEN workflow runner state SHALL be `[]` coerced to `{}`; array `context` (AG-UI context items) SHALL NOT be used as workflow state.
- WHEN `resume[].interruptId` does not match a run of that workflow THEN 404.
- WHEN the run is not awaiting input THEN 422 (same as M4 resume).
- WHEN JSON Patch would be empty THEN no `STATE_DELTA` event.

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| AGUI-01 | P1: Accept RunAgentInput | Execute | Verified |
| AGUI-02 | P1: Last user message mapping | Execute | Verified |
| AGUI-03 | P1: Echo threadId/runId | Execute | Verified |
| AGUI-04 | P1: `{ message }` fallback | Execute | Verified |
| AGUI-05 | P1: Ignore `tools[]`; playground isolation | Execute | Verified |
| AGUI-06 | P2: MESSAGES_SNAPSHOT | Execute | Verified |
| AGUI-07 | P2: STATE_SNAPSHOT / STATE_DELTA | Execute | Verified |
| AGUI-08 | P2: Canonical HITL dual-emit + resume[] | Execute | Verified |
| AGUI-09 | P2: Docs + Connect Panel | Execute | Verified |

**ID format:** `AGUI-NN`  
**Coverage:** 9 total

---

## Success Criteria

- [ ] CopilotKit `HttpAgent` can POST `RunAgentInput` to Studio AG-UI routes without a host translator.
- [ ] Lifecycle events echo client `threadId`/`runId`.
- [ ] Human workflow pause is both CUSTOM (M4) and canonical interrupt; both resume paths complete the run.
- [ ] Vercel integrate + Studio playground tests remain green.
