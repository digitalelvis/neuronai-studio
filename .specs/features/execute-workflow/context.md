# Execute Workflow — Context

**Gathered:** 2026-08-02  
**Spec:** [.specs/features/execute-workflow/spec.md](./spec.md)  
**Status:** Ready for design / tasks  
**Source:** Plan discussion + Langflow Run Flow reference screenshots; answers locked 2026-08-02.

---

## Feature Boundary

Deliver a canvas node type **`run_workflow`** (UI: “Run Workflow” / “Executar Workflow”) that executes another Studio workflow either as a **control-flow step** or as an **agent tool** (Tool Mode), with searchable workflow select and **message + free state map** inputs — without depending on `state_schema` in v1.

---

## Implementation Decisions

| ID | Decision |
|----|----------|
| D1 | **v1 includes both** Step Mode and Tool Mode |
| D2 | Inputs: **message** + **free state map** `key → value \| {{template}}` — **no** `state_schema`-driven fields in v1 |
| D3 | Node type key: `run_workflow` (i18n labels EN/pt_BR) |
| D4 | Reuse `toolable` + Actions + `toolset` handle contract from [canvas-tool-mode](../canvas-tool-mode/spec.md) |
| D5 | Child `StudioRun` sets `parent_run_id` (same nested metering pattern as agents) |
| D6 | Disallow self-call (same definition id as parent graph); max nesting depth = **3** |
| D7 | Child Human/HITL interrupt in v1: **fail** the node/tool with a clear error; do not propagate resume to parent |

### Tool Mode message merge (agent discretion, locked in design)

- Node `message` is the **default** when the caller does not supply a usable input string.
- Caller tool `input` **wins** as the child run `message` / `input` when non-empty.
- `state_map` on the node always applies (templates resolved against parent state at Step time; against available context at Tool time — see design).

### Picker scope (agent discretion)

- Combobox lists Studio workflows (`source = studio`) available to the Editor catalog.
- Exclude the workflow currently open in the editor from the list (and reject if somehow selected).
- Code-source / locked workflows follow the same eligibility rules as the Studio workflows index unless Editor already filters them — match Editor’s agents list pattern.

### Agent's Discretion

- Category for palette: `logic` (composition) unless existing i18n/category patterns favor `ai`.
- Default `output_key`: `child_output`.
- Tool exposure `slug_prefix`: `run_workflow`.
- Serialize non-string child output with `json_encode` for parent state / tool result.
- Nesting depth counted via walk of `parent_run_id` on workflow runs and/or an explicit `__workflow_nesting_depth` stamp on child input — design picks one consistent approach.

---

## Explicitly deferred (out of v1)

- `state_schema`-driven dynamic inspector fields
- Authoring UI for `state_schema`
- Attachments passthrough to child
- Propagate Human/HITL from child to parent
- Static cross-workflow cycle detection beyond depth limit
- Code workflows in picker as first-class (unless already in Editor list)

---

## Related

- Deferred from: [canvas-tool-mode/context.md](../canvas-tool-mode/context.md) (“Nested workflow-as-tool”)
- Runtime: `WorkflowRunner`, `GraphValidator`, `ToolResolver`, `NodeAsTool`
- Plan: `.cursor/plans/executar_workflow_spec_25a4f5af.plan.md`
