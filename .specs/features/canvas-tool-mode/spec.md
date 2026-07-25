# Canvas Tool Mode Specification

## Problem Statement

Studio can place many Agent nodes on a workflow graph and wire them with control-flow edges, but cannot make one agent **delegate** to another via LLM tool-calling. Authors who want a supervisor + specialists pattern must fake it with conditions or leave the canvas. Langflow solves this with **Tool Mode**: the same Agent component flips from a graph step into a toolset source with an Actions modal (slug / description / params).

Neuron AI has no SubAgent API; the idiomatic path is a custom `Tool` that invokes another agent. Studio must expose that as a first-class canvas pattern, with a **toolable** contract so other node types can opt in later.

## Goals

- [ ] Authors toggle Agent nodes into Tool Mode and connect Toolset → supervisor `tools`.
- [ ] Runtime exposes the specialist as a Neuron tool (`slug` / `description` / caller-controlled input) and executes it on tool-call.
- [ ] Codegen + docs cover supervisor + specialist Tool Mode; metering nests under parent run.

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Tool Mode for `llm` / `rag` / `invoke` / custom | v1 = Agent only; meta stays extensible |
| Handoff / irreversible turn transfer | Different protocol |
| Nested workflow-as-tool | Separate feature |
| Advanced memory isolation for specialists | Default in-memory / child thread |
| Changing Neuron core | Composition via Studio Tool wrapper |

**Context:** [context.md](./context.md)

---

## User Stories

### P1: Toggle Tool Mode on Agent ⭐ MVP

**User Story**: As a Studio author, I want to switch an Agent node into Tool Mode so that it becomes a tool component instead of a graph step.

**Why P1**: Without the duality, there is no authoring surface for agent-as-tool.

**Acceptance Criteria**:

1. WHEN node type meta has `toolable: true` THEN the Agent node chrome or inspector SHALL expose a Tool Mode toggle.
2. WHEN `tool_mode` is false THEN the node SHALL show message Input and source handle `default` (Response), matching current Agent step behavior.
3. WHEN `tool_mode` is set true THEN the node SHALL hide message Input, show an Actions control, expose source handle `toolset`, and SHALL NOT accept control-flow participation (incoming/outgoing `default` edges invalid or stripped).
4. WHEN Tool Mode is toggled off THEN toolset edges SHALL be stripped and Step Mode UI restored.
5. WHEN a non-`toolable` node type is selected THEN Tool Mode toggle SHALL NOT appear.

**Independent Test**: Place Agent → toggle Tool Mode on → Input gone, Actions + toolset handle visible; toggle off → Input back.

---

### P1: Configure tool exposure (Actions modal) ⭐ MVP

**User Story**: As a Studio author, I want to configure slug, description, and parameters for the agent-as-tool so the supervisor LLM knows when and how to call it.

**Why P1**: Tool quality depends on name/description; Langflow parity requires Actions modal.

**Acceptance Criteria**:

1. WHEN author opens Actions THEN system SHALL show modal with slug, description, and parameters section.
2. WHEN slug is empty THEN system SHALL default from meta `tool_exposure.slug_prefix` (e.g. `call_agent`) and persist on save.
3. WHEN description is empty THEN system SHALL use a sensible default derived from agent instructions or meta default.
4. WHEN parameters are shown THEN the primary input SHALL be marked as controlled by the calling agent (not a fixed canvas template).
5. WHEN slug is invalid for a function name (empty after normalize, illegal chars) THEN validation SHALL reject before run/export.

**Independent Test**: Open Actions → set slug `research_agent` + description → values persist in node `data.tool_exposure`.

---

### P1: Wire Toolset to supervisor ⭐ MVP

**User Story**: As a Studio author, I want to connect a Tool Mode agent’s Toolset handle to a supervisor Agent’s tools handle so the specialist is available as a tool at runtime.

**Why P1**: Completes the canvas graph for supervisor pattern.

**Acceptance Criteria**:

1. WHEN source handle is `toolset` and source node has `tool_mode: true` THEN connection to an Agent target handle `tools` SHALL be allowed.
2. WHEN target is not an Agent or handle is not `tools` THEN connection SHALL be rejected.
3. WHEN supervisor is in `existing` mode THEN `tools` target handle SHALL still be visible so toolset edges can attach (D8).
4. WHEN graph validates THEN toolset edges SHALL be excluded from control-flow / cycle detection (same class as tool/mcp binding edges).
5. WHEN Tool Mode agent has no toolset edge to any supervisor THEN run/export MAY warn but SHALL NOT treat the node as a control-flow step.

**Independent Test**: Specialist (tool mode) → tools pin of supervisor; GraphValidator accepts; control-flow path start→supervisor→stop only.

---

### P1: Runtime NodeAsTool execution ⭐ MVP

**User Story**: As an operator running a workflow, I want the supervisor’s tool-call to invoke the Tool Mode agent and return its reply as the tool result.

**Why P1**: Authoring without runtime is useless.

**Acceptance Criteria**:

1. WHEN supervisor runs and model calls the exposed slug THEN system SHALL resolve `node:{id}` binding, build a Neuron `Tool` with exposure name/description, and invoke the specialist via `AgentRunner` (inline/existing config from that node).
2. WHEN specialist completes THEN tool result SHALL be the specialist response content (string) returned to the supervisor tool loop.
3. WHEN specialist fails THEN tool result SHALL surface an error string (or Neuron tool error path) without crashing the whole workflow unless unrecoverable.
4. WHEN specialist runs under a workflow THEN usage SHALL nest with `parent_run_id` (existing nested agent metering).
5. WHEN supervisor has AgentDefinition tools AND toolset bindings THEN both SHALL be available to the model.

**Independent Test**: Unit/integration: mock supervisor tool-call → specialist `runInline` invoked with caller input → content returned as tool result.

---

### P2: Codegen + docs + template

**User Story**: As a host exporting workflows, I want Tool Mode specialists emitted as tools on the supervisor and documented for authors.

**Why P2**: Production parity after canvas/runtime MVP.

**Acceptance Criteria**:

1. WHEN native export runs THEN Tool Mode agent nodes SHALL NOT become linear workflow Nodes; supervisor codegen SHALL include NodeAsTool/AgentAsTool (or equivalent binding) for each toolset edge.
2. WHEN docs update THEN ai-nodes + canvas-editor + custom-node-types SHALL describe `toolable`, Tool Mode, Actions, Toolset.
3. WHEN a template `supervisor-specialist-tool-mode` (or similar) is added THEN it SHALL demo one supervisor + one Tool Mode specialist.

**Independent Test**: Export graph → PHP has no specialist `*Node` on control path; supervisor tools array includes specialist tool.

---

## Edge Cases

- WHEN two Tool Mode agents share the same slug on one supervisor THEN validation SHALL reject (unique slug per supervisor).
- WHEN Tool Mode agent is `existing` with missing `agent_id` THEN validation SHALL fail like Step Mode.
- WHEN author connects Tool Mode agent as control-flow successor THEN UI/validator SHALL reject or auto-strip.
- WHEN tool approval is enabled on supervisor THEN specialist tool-calls SHALL respect approval middleware (same as other tools).
- WHEN specialist itself has tools (definition or bindings) THEN those tools SHALL run inside the nested agent visit, not as sibling canvas steps.

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| CTM-01 | P1: Toggle Tool Mode | Tasks | Pending |
| CTM-02 | P1: Actions modal exposure | Tasks | Pending |
| CTM-03 | P1: Wire Toolset | Tasks | Pending |
| CTM-04 | P1: Runtime NodeAsTool | Tasks | Pending |
| CTM-05 | P2: Codegen + docs + template | Tasks | Pending |

**Coverage:** 5 total, 5 mapped to tasks (CTM-T1…T10)

---

## Success Criteria

- [ ] Demo: start → supervisor agent (tools) ← toolset ← specialist (Tool Mode) → stop; supervisor delegates via tool-call.
- [ ] GraphValidator + canvas reject illegal Tool Mode / control-flow mixes.
- [ ] Nested metering rolls specialist usage to parent.
- [ ] Export produces runnable PHP without linear specialist nodes.
- [ ] Docs + template ship with the feature.
