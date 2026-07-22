# Canvas Invoke Node Specification

**Line:** `v0.9.x` (post-M8 P2) · **Tasks:** [tasks.md](./tasks.md)  
**Requirement IDs:** `INV-xx` · **Date:** 2026-07-21

## Problem Statement

Hosts need a simple way to call allowlisted PHP from a workflow graph without registering a full custom node type. Today the only extension paths are `NeuronAIStudio::registerNode` (full type + executor) or the `tool` node’s `tool_class` (AI-oriented, not a general hook). Freeform eval in the canvas is unsafe. Operators need a first-class **invoke** node that runs a host-approved callable and writes the result into workflow state.

## Goals

- [x] Ship a canvas node type `invoke` (category `logic`) with `hook_class` + `output_key`.
- [x] Enforce a fail-closed FQCN allowlist via `config('neuronai-studio.invoke_hooks')`.
- [x] At runtime, resolve the class from the container, call `__invoke(WorkflowState)`, store the return value in state.
- [x] Validate graphs before run; emit native codegen for export; document when to use invoke vs a custom node.

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Freeform `invoke_body` / eval | Unsafe; builder tools already cover prototype PHP elsewhere |
| TraceDetail URL bridge | Separate deferred P2 |
| Named method other than `__invoke` | Keep contract minimal |
| Checkpoint decorator on invoke | Cheap PHP; host can use custom node if needed |
| Parameter mapping UI (`$state` keys) | Pass full `WorkflowState`; hook reads what it needs |

---

## User Stories

### P1: Allowlisted invoke node on the canvas ⭐ MVP

**User Story**: As a Studio author, I want an Invoke node on the logic palette so that I can call host PHP at a point in the graph without building a custom node type.

**Why P1**: This is the product gap called out as deferred P2 after M7/M8.

**Acceptance Criteria**:

1. WHEN `node_types.invoke` is registered THEN the canvas palette SHALL show an Invoke node under the logic category (start/stop still hidden as today).
2. WHEN configuring an invoke node THEN the inspector SHALL expose `hook_class` (FQCN string) and `output_key` (default `invoke_result`).
3. WHEN the node runs successfully THEN the workflow state SHALL contain `output_key` set to the hook’s return value and the node SHALL leave via handle `default`.
4. WHEN `hook_class` is empty, missing from allowlist, not loadable, or not callable via `__invoke` THEN GraphValidator SHALL reject the graph and/or the executor SHALL fail with a clear error (no silent skip).

**Independent Test**: Allowlist a test hook class → place invoke node → run workflow → state has expected output under `output_key`.

---

### P1: Fail-closed allowlist ⭐ MVP

**User Story**: As a host operator, I want only FQCNs I list in config to be invokable so that the canvas cannot call arbitrary PHP classes.

**Why P1**: Security boundary; same spirit as MCP stdio allowlist / webhook hosts.

**Acceptance Criteria**:

1. WHEN `invoke_hooks` is empty THEN any invoke node with a `hook_class` SHALL fail validation / runtime (fail-closed).
2. WHEN `hook_class` is not in `invoke_hooks` THEN validation and runtime SHALL reject it even if `class_exists` is true.
3. WHEN `hook_class` is on the allowlist and implements `__invoke(WorkflowState): mixed` (or compatible) THEN runtime SHALL resolve via `app($hookClass)` and invoke it with the current state.

**Independent Test**: Class exists but not allowlisted → validator error; after adding to config → run succeeds.

---

### P1: Codegen + docs

**User Story**: As a developer exporting a native workflow, I want invoke nodes emitted as direct host calls and documented so that production code matches Studio behavior.

**Acceptance Criteria**:

1. WHEN codegen runs for an invoke node THEN it SHALL emit a call to the configured FQCN (with a comment that the host must keep `invoke_hooks` aligned).
2. WHEN docs are updated THEN extending/custom-node-types, logic nodes, and configuration SHALL describe invoke vs full custom node and the `invoke_hooks` key.

**Independent Test**: Snapshot or string assert on generated PHP for an invoke node; docs contain `invoke_hooks`.

---

## Requirement Traceability

| ID | Story | Priority |
| ---- | ----- | -------- |
| INV-01 | Palette + inspector + runtime | P1 |
| INV-02 | Fail-closed allowlist | P1 |
| INV-03 | GraphValidator rules | P1 |
| INV-04 | Native codegen | P1 |
| INV-05 | Docs | P1 |

## Success Criteria

- [x] Invoke node appears in logic palette and persists `hook_class` / `output_key`
- [x] Empty allowlist or off-list class cannot run
- [x] Happy-path unit tests green; validator + codegen covered
- [x] Docs updated; ROADMAP/STATE mark invoke done (TraceDetail bridge still deferred)
