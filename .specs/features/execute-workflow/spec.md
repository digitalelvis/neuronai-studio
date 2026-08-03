# Execute Workflow (Run Flow) Specification

## Problem Statement

Studio authors cannot reuse one workflow from inside another — neither as a control-flow step nor as an agent tool. Langflow covers this with **Run Flow**. Canvas Tool Mode shipped agent-as-tool but explicitly deferred nested workflow-as-tool. Without a first-class `run_workflow` node, authors fake composition via Invoke hooks or duplicate graphs.

## Goals

- [ ] Authors place an **Executar Workflow** / **Run Workflow** node, pick a Studio workflow via searchable select, pass message + free state map, and run it as a graph step.
- [ ] The same node type supports **Tool Mode** (`toolable`), exposing the target workflow as a Neuron tool for supervisor agents.
- [ ] Child runs nest under the parent via `parent_run_id`; self-call and excessive nesting are rejected.

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Dynamic fields from `state_schema` | v1 uses free state map only (D2) |
| UI to author `state_schema` | Separate feature |
| Attachments forwarded to child | Not requested; can use state map later |
| Propagate Human/HITL interrupt from child to parent | v1 fails node/tool with clear error (D7) |
| Code-source workflows in picker | Studio definitions only (align with Editor catalog) |
| Static cross-definition cycle detection (A→B→A) | Depth limit = 3 covers abuse; static analysis = P3 |
| Changing Neuron core | Composition via Studio executor + Tool wrapper |

**Context:** [context.md](./context.md)  
**Design:** [design.md](./design.md)  
**Tasks:** [tasks.md](./tasks.md)

---

## User Stories

### P1: Palette + Step Mode config ⭐ MVP

**User Story**: As a Studio author, I want a `run_workflow` node with a searchable workflow picker, message template, and free state key/value map so I can compose workflows on the canvas.

**Why P1**: Without authoring surface there is no feature.

**Acceptance Criteria**:

1. WHEN node type meta registers `run_workflow` THEN the palette SHALL list it with i18n label (EN: “Run Workflow”, pt_BR: “Executar Workflow”) and category suitable for discovery (logic or ai).
2. WHEN the node is selected THEN the inspector SHALL show a searchable Combobox of Studio workflows (id, name, slug) supplied by the Editor (`workflowsForCanvas`), excluding the workflow currently being edited.
3. WHEN the author configures the node THEN `data` SHALL persist `workflow_id`, `message` (template string), `state_map` (array of `{key, value}`), and `output_key` (default e.g. `child_output`).
4. WHEN `tool_mode` is false THEN the node SHALL expose control-flow handles target/source `default` (Step Mode).
5. WHEN no workflow is selected THEN save/run validation SHALL reject with a clear error.

**Independent Test**: Drop `run_workflow` → search and select another Studio workflow → set message `{{input}}` + one state row → values persist in graph JSON.

---

### P1: Runtime Step Mode execution ⭐ MVP

**User Story**: As an operator running a parent workflow, I want the `run_workflow` step to execute the selected child workflow with resolved message/state and write the result into parent state.

**Why P1**: Authoring without runtime is useless.

**Acceptance Criteria**:

1. WHEN the executor runs THEN it SHALL resolve `workflow_id` to a Studio `WorkflowDefinition` and build runner input `{ message, input: message, state: resolvedMap }` with templates evaluated against the parent `WorkflowState`.
2. WHEN the child starts THEN the child `StudioRun` SHALL set `parent_run_id` to the parent run id.
3. WHEN the child completes successfully THEN the executor SHALL write the child output (string, or JSON-encoded if structured) into parent state at `output_key` and return handle `default`.
4. WHEN the child fails or status is non-success THEN the executor SHALL surface a clear error (fail the parent step) without silently continuing.
5. WHEN the child pauses for Human/HITL (or equivalent interrupt) THEN the executor SHALL treat it as failure with a clear message (no parent resume bridge in v1).

**Independent Test**: Parent start → `run_workflow` → stop; child run appears with `parent_run_id`; parent state has `output_key` set.

---

### P1: Tool Mode for run_workflow ⭐ MVP

**User Story**: As a Studio author, I want to toggle `run_workflow` into Tool Mode and wire Toolset → supervisor `tools` so an agent can invoke another workflow via LLM tool-calling.

**Why P1**: Locked as both modes in v1 (D1); closes the deferred “nested workflow-as-tool” gap.

**Acceptance Criteria**:

1. WHEN node meta has `toolable: true` THEN the `run_workflow` chrome/inspector SHALL expose Tool Mode toggle (same contract as Agent Tool Mode).
2. WHEN `tool_mode` is true THEN the node SHALL expose source handle `toolset`, SHALL NOT participate in control-flow, and SHALL show Actions for `tool_exposure` (slug, description); Step message remains as **default** message merged with caller input (caller input wins / becomes the run message).
3. WHEN `toolset` connects to an Agent `tools` handle THEN GraphValidator and connection rules SHALL accept it (same class as agent Tool Mode).
4. WHEN the supervisor tool-calls the exposed slug THEN runtime SHALL resolve `node:{id}`, run the target workflow via `WorkflowAsTool` (or equivalent), nest `parent_run_id`, and return child output as the tool result string.
5. WHEN Tool Mode slug is invalid THEN validation SHALL reject before run/export.

**Independent Test**: Supervisor ← toolset ← `run_workflow` (Tool Mode); mock tool-call → child `WorkflowRunner` invoked → string result returned.

---

### P1: Safety — self-call and nesting depth ⭐ MVP

**User Story**: As a platform operator, I want self-invocation and unbounded nesting blocked so recursive workflow composition cannot stack-overflow or loop forever.

**Why P1**: Nested runners without guards are a production risk.

**Acceptance Criteria**:

1. WHEN `workflow_id` equals the parent definition id (graph being edited / running) THEN GraphValidator SHALL reject self-reference.
2. WHEN runtime nesting depth (count of workflow ancestors via `parent_run_id` / nesting counter) would exceed **3** THEN the executor/tool SHALL fail with a clear error.
3. WHEN the selected workflow is missing or not Studio-eligible THEN validation/runtime SHALL fail clearly.

**Independent Test**: Configure self-id → validate error; force depth 4 in unit test → error.

---

### P2: Codegen + docs + template

**User Story**: As a host exporting workflows, I want `run_workflow` represented in native export and documented for authors, with a demo template.

**Why P2**: Production parity after canvas/runtime MVP.

**Acceptance Criteria**:

1. WHEN native export runs THEN Step Mode `run_workflow` SHALL emit a callable that invokes the child workflow (or documented Studio runtime helper) with mapped message/state; Tool Mode SHALL bind as a tool on the supervisor (snapshot or ref per existing Tool Mode export pattern).
2. WHEN docs update THEN canvas-editor + node-types guides + custom-node-types SHALL describe `run_workflow`, Step vs Tool Mode, message + state map.
3. WHEN a template is added THEN it SHALL demo parent → `run_workflow` → stop (and optionally supervisor + Tool Mode `run_workflow`).

**Independent Test**: Export sample graph; docs mention Run Workflow; template loads in Studio.

---

## Edge Cases

- WHEN `state_map` has empty keys THEN system SHALL ignore those rows or reject on validate (prefer reject empty keys).
- WHEN template `{{missing}}` is unresolved THEN system SHALL follow existing template resolution behavior (empty/literal — match agent message templates).
- WHEN child completes with empty output THEN system SHALL write empty string (or null-coalesced empty) to `output_key`.
- WHEN Tool Mode node has no toolset edge THEN run/export MAY warn but SHALL NOT treat the node as a control-flow step.
- WHEN two Tool Mode `run_workflow` nodes share the same slug under one supervisor THEN GraphValidator SHALL reject (unique slug per supervisor — reuse Agent Tool Mode rule).

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| EW-01 | P1: Palette + Step Mode config | Tasks | Pending |
| EW-02 | P1: Runtime Step Mode execution | Tasks | Pending |
| EW-03 | P1: Tool Mode for run_workflow | Tasks | Pending |
| EW-04 | P1: Safety — self-call and nesting | Tasks | Pending |
| EW-05 | P2: Codegen + docs + template | Tasks | Pending |

**ID format:** `EW-[NUMBER]`

**Status values:** Pending → In Design → In Tasks → Implementing → Verified

**Coverage:** 5 total, 5 mapped to design/tasks, 0 unmapped

---

## Success Criteria

- [ ] Demo: parent start → `run_workflow` (message + state map) → stop; child run nested; parent state has output
- [ ] Demo: supervisor Agent ← toolset ← `run_workflow` Tool Mode; tool-call runs child and returns string
- [ ] Self-call rejected at validate; nesting depth > 3 fails at runtime
- [ ] No dependency on `state_schema` for inspector fields in v1
