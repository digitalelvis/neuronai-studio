# Execute Workflow — Tasks

**Spec**: [spec.md](./spec.md)  
**Design**: [design.md](./design.md)  
**Context**: [context.md](./context.md)  
**Status**: Done — EW-T1…T10 
**Linha sugerida:** `v2.1.x` · milestone **M13**

---

## Execution Plan

```
EW-T1 → EW-T2 → EW-T3
EW-T1 → EW-T4 [P]
EW-T2 → EW-T5
EW-T3, EW-T5 → EW-T6
EW-T4, EW-T2 → EW-T7
EW-T6, EW-T7 → EW-T8
EW-T8 → EW-T9 [P]
EW-T8 → EW-T10
```

---

### EW-T1 — Meta `run_workflow` + registry + i18n (EW-01)

**What**: Register node type `run_workflow` with `toolable` + `tool_exposure` defaults; wire executor binding in service provider; EN/pt_BR labels via registry i18n.  
**Where**: `config/neuronai-studio.php`, `NeuronAIStudioServiceProvider.php`, `NodeTypeRegistry` / lang catalogs as needed  
**Depends on**: —  
**Reuses**: Agent `toolable` meta pattern  
**Done when**:

- [x] Canvas `nodeTypes` includes `run_workflow` with `toolable: true`
- [x] Label resolves in `en` and `pt_BR`

**Tests**: unit/config assert if existing registry tests  
**Gate**: package unit subset if touched  
**Status**: Done

---

### EW-T2 — GraphValidator rules (EW-01, EW-03, EW-04)

**What**: Validate `workflow_id` present + exists + ≠ current definition; Tool Mode / toolset rules for `run_workflow`; unique exposure slug per supervisor; exclude tool-mode nodes from CF; reject empty `state_map` keys.  
**Where**: `src/Runtime/GraphValidator.php` (+ tests)  
**Depends on**: EW-T1  
**Reuses**: Agent Tool Mode validation  
**Done when**:

- [x] Self-ref and missing target produce clear errors
- [x] Valid Step and Tool Mode graphs pass

**Tests**: `GraphValidator` feature/unit cases  
**Status**: Done

---

### EW-T3 — Editor `workflowsForCanvas` payload (EW-01)

**What**: Pass Studio workflow list (id, name, slug) into canvas config; exclude current workflow id.  
**Where**: `src/Http/Livewire/Workflows/Editor.php`, frontend config consumer  
**Depends on**: EW-T1  
**Reuses**: agents-for-canvas payload pattern  
**Done when**:

- [x] React shell receives `workflows` (or `workflowsForCanvas`) array
- [x] Current workflow id absent from list

**Tests**: Livewire/unit if pattern exists; else manual  
**Status**: Done

---

### EW-T4 — Canvas UI: inspector + handles + Tool Mode (EW-01, EW-03) [P]

**What**: `NodeConfigForm` branch: Combobox search, message, state map rows, `output_key`; Tool Mode toggle; handles (`default` vs `toolset`); strip edges on toggle; wire Actions modal.  
**Where**: `NodeConfigForm.jsx`, `WorkflowNode.jsx`, `graph.js` / `nodeUtils.js`, `ToolExposureModal` reuse  
**Depends on**: EW-T1  
**Reuses**: Agent combobox + Tool Mode chrome  
**Done when**:

- [x] Selecting a workflow + state row persists in graph JSON
- [x] Tool Mode flips handles and strips CF edges

**Tests**: none required (JS); manual checklist  
**Status**: Done

---

### EW-T5 — `WorkflowRunner` nested `parent_run_id` + depth stamp (EW-02, EW-04)

**What**: Ensure nested runs can set `parent_run_id`; support `__workflow_nesting_depth` on input; document internal keys.  
**Where**: `src/Runtime/WorkflowRunner.php`, `StudioRun` create path  
**Depends on**: EW-T2 (can start after T1 if careful; prefer after T2 for validate alignment)  
**Reuses**: AgentRunner nesting  
**Done when**:

- [x] Child run persists `parent_run_id` when provided
- [x] Depth stamp readable by executor

**Tests**: unit/feature on runner create  
**Status**: Done

---

### EW-T6 — `RunWorkflowNodeExecutor` Step Mode (EW-02, EW-04)

**What**: Implement executor: resolve templates, depth guard (≤3), run target, HITL→error, write `output_key`, return `default`.  
**Where**: `src/Runtime/NodeExecutors/RunWorkflowNodeExecutor.php` (+ register)  
**Depends on**: EW-T1, EW-T5  
**Reuses**: Template helpers, WorkflowRunner  
**Done when**:

- [x] Parent step writes child output to state
- [x] Depth > 3 and HITL fail with clear errors

**Tests**: executor unit/integration with faked runner or lightweight definitions  
**Status**: Done

---

### EW-T7 — `WorkflowAsTool` + ToolResolver (EW-03)

**What**: Tool wrapper for `run_workflow` tool-mode nodes; message merge (caller wins); state_map apply; nest parent_run_id; return string result.  
**Where**: `src/Runtime/Tools/WorkflowAsTool.php` (or under Tools/), `ToolResolver.php`, `GraphContext` if needed for binding shape  
**Depends on**: EW-T4 (binding shape), EW-T5  
**Reuses**: `NodeAsTool` / ToolResolver `node:` path  
**Done when**:

- [x] Tool-call invokes child workflow and returns string
- [x] Metering nests via `parent_run_id`

**Tests**: unit/integration ToolResolver + WorkflowAsTool  
**Status**: Done

---

### EW-T8 — Integration smoke: Step + Tool Mode graphs (EW-01…04)

**What**: End-to-end feature tests (or harness fixtures) for parent→run_workflow→stop and supervisor←toolset←run_workflow.  
**Where**: `tests/` feature  
**Depends on**: EW-T6, EW-T7  
**Done when**:

- [x] Both demos green in CI
- [x] Self-call validate covered

**Tests**: feature tests  
**Status**: Done

---

### EW-T9 — Codegen (EW-05) [P]

**What**: Native export for Step `run_workflow` and Tool Mode binding (align with Agent Tool Mode export strategy).  
**Where**: codegen / `NodeCodeGenerator` (or equivalent)  
**Depends on**: EW-T8  
**Done when**:

- [x] Exported PHP includes nested run or tool binding without dropping the node silently

**Tests**: codegen snapshot/unit if existing  
**Status**: Done

---

### EW-T10 — Docs + template (EW-05)

**What**: Document node in canvas-editor + node-types + custom-node-types; add demo template parent/child (optional Tool Mode).  
**Where**: `docs/guides/...`, templates path  
**Depends on**: EW-T8  
**Done when**:

- [x] Docs describe Step + Tool Mode + message/state map
- [x] Template loads in Studio

**Tests**: none  
**Status**: Done

---

## Traceability

| Requirement | Tasks |
| ----------- | ----- |
| EW-01 | EW-T1, EW-T2, EW-T3, EW-T4, EW-T8 |
| EW-02 | EW-T5, EW-T6, EW-T8 |
| EW-03 | EW-T2, EW-T4, EW-T7, EW-T8 |
| EW-04 | EW-T2, EW-T5, EW-T6, EW-T8 |
| EW-05 | EW-T9, EW-T10 |

---

## Notes for Execute

- Do not implement `state_schema` UI.
- Do not propagate child HITL to parent.
- Prefer atomic commits per task (or per small task group) on feature branch `feat/execute-workflow`.
