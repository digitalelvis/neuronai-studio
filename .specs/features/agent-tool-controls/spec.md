# Agent Tool Controls Specification

## Problem Statement

Neuron already runs multiple tool rounds inside a single agent node visit (`toolMaxRuns`, optional `parallelToolCalls`), but Studio does not expose those knobs and only emits `tool_call` / `tool_result` SSE **after** the visit finishes (from chat history). Operators cannot tune loop depth or see tools live mid-stream.

## Goals

- [ ] Persist and edit `tool_max_runs` and `parallel_tool_calls` on `AgentDefinition` and optional node overrides.
- [ ] Apply knobs when building the Neuron Agent in `AgentRunner` / `DynamicAgent`.
- [ ] Emit live `tool_call` / `tool_result` SSE during streaming (and best-effort on blocking path).
- [ ] Document + codegen the new fields.

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Reimplement Neuron tool loop | Already in vendor |
| Cross-iteration multi-turn | Covered by AMA + loop nodes |
| Tool approval changes | Separate feature |
| Changing default Neuron behavior silently | Defaults stay Neuron defaults (10 / false) unless configured |

**Context:** [m6-runtime-agent/context.md](../m6-runtime-agent/context.md)

---

## User Stories

### P1: Configure tool loop on agent ⭐ MVP

**User Story**: As a Studio author, I want to set max tool rounds and parallel tool calls on an agent so that long tool chains and parallel tools behave as I expect.

**Acceptance Criteria**:

1. WHEN `AgentDefinition` is saved with `tool_max_runs` (int ≥ 1) and/or `parallel_tool_calls` (bool) THEN values SHALL persist and round-trip in the agent editor.
2. WHEN an agent node overrides `data.tool_max_runs` / `data.parallel_tool_calls` THEN those values SHALL win for that node visit.
3. WHEN neither is set THEN system SHALL use Neuron defaults (`toolMaxRuns=10`, `parallelToolCalls=false`).
4. WHEN `AgentRunner` builds the agent THEN it SHALL call Neuron `toolMaxRuns()` / `parallelToolCalls()` with the resolved values.

**Independent Test**: Set `tool_max_runs=2` → agent with tools that would need 3 rounds stops at cap (or Neuron equivalent behavior).

---

### P1: Live tool SSE mid-stream ⭐ MVP

**User Story**: As an operator in the test harness, I want to see tool calls and results appear while the agent is still streaming so that I can debug without waiting for step completion.

**Acceptance Criteria**:

1. WHEN agent node runs with `stream` and tools fire THEN system SHALL emit SSE `tool_call` / `tool_result` (same payload shape as today) **before** `step_completed`.
2. WHEN token streaming is active THEN `token` events MAY interleave with tool events.
3. WHEN blocking (non-stream) path runs THEN system SHALL still emit tool events (may be batched from history if live chunks unavailable) before `step_completed`.
4. WHEN the same tool event would be duplicated from post-history extract THEN system SHALL dedupe.

**Independent Test**: Mock stream yielding tool chunks → assert order `step_started` → `tool_call` → `tool_result` → `token*` → `step_completed`.

---

### P2: Codegen + docs

**User Story**: As a host exporting workflows, I want generated agent setup to include tool loop knobs.

**Acceptance Criteria**:

1. WHEN codegen emits agent bootstrap THEN it SHALL include `toolMaxRuns` / `parallelToolCalls` when non-default.
2. Docs update creating-agents, ai-nodes, runtime-and-traces, configuration.

---

## Edge Cases

- WHEN `tool_max_runs` is 0 or negative THEN validation SHALL reject (422 / Livewire error).
- WHEN `parallel_tool_calls=true` but Neuron/provider does not support parallel tools THEN behavior follows Neuron (document limitation).
- WHEN tool approval is required THEN live tool SSE MUST NOT bypass approval pause semantics.

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| ATC-01 | P1: Configure tool loop | Tasks | Pending |
| ATC-02 | P1: Live tool SSE | Tasks | Pending |
| ATC-03 | P2: Codegen + docs | Tasks | Pending |

---

## Success Criteria

- [ ] Agent editor + node inspector expose knobs.
- [ ] Runtime applies knobs to Neuron Agent.
- [ ] Harness shows tools mid-stream.
- [ ] Tests cover apply + live emit + dedupe.
