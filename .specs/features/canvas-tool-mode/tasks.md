# Canvas Tool Mode — Tasks

**Spec**: [spec.md](./spec.md)  
**Design**: [design.md](./design.md)  
**Context**: [context.md](./context.md)  
**Status**: Execute in progress  
**Linha**: `v1.1.x` · **AD-025** · branch `feat/canvas-tool-mode`

---

## Execution Plan

```
CTM-T1 → CTM-T2 → CTM-T3
CTM-T1 → CTM-T4 [P]
CTM-T2, CTM-T3 → CTM-T5
CTM-T3, CTM-T4 → CTM-T6
CTM-T5, CTM-T6 → CTM-T7
CTM-T7 → CTM-T8
CTM-T7 → CTM-T9 [P]
CTM-T8, CTM-T9 → CTM-T10
```

---

### CTM-T1 — Meta `toolable` + canvas payload (CTM-01)

**What**: Add `toolable` + `tool_exposure` defaults on `agent` node meta; expose via `NodeTypeRegistry::forCanvas()`.  
**Where**: `config/neuronai-studio.php`, `NodeTypeRegistry.php`, Livewire Editor nodeTypes payload if needed  
**Done when**:

- [x] Canvas receives `toolable: true` for agent
- [x] Other built-in types omit or `toolable: false`

**Tests**: unit registry/meta if existing pattern; else config assert in feature test  
**Status**: Done

---

### CTM-T2 — GraphValidator Tool Mode rules (CTM-01, CTM-03)

**What**: Validate `tool_mode` agents; toolset→tools edges; unique slug per supervisor; exclude tool-mode nodes from CF reachability; reject CF edges to/from tool_mode.  
**Where**: `src/Runtime/GraphValidator.php`  
**Done when**:

- [x] Invalid graphs produce clear errors
- [x] Valid supervisor+specialist graph passes

**Tests**: `GraphValidatorTest` cases  
**Status**: Done

---

### CTM-T3 — GraphContext toolset bindings (CTM-03, CTM-04)

**What**: Collect `sourceHandle=toolset` into `toolBindingsFor` as `{ ref: "node:{id}", ... exposure }`; keep binding edges out of CF helpers.  
**Where**: `GraphContext.php`, `graph.js` `isToolBindingEdge` if mirrored  
**Done when**:

- [x] toolset edge appears in bindings array
- [x] tool/mcp bindings still work

**Tests**: `GraphContextToolBindingsTest` (or new)  
**Status**: Done

---

### CTM-T4 — Canvas UI: toggle, handles, strip edges (CTM-01, CTM-03) [P]

**What**: Tool Mode toggle; Input↔Actions; handles (`toolset` source; supervisor `tools` for existing+inline); connection rules; strip edges on toggle.  
**Where**: `WorkflowNode.jsx`, `WorkflowCanvas.jsx`, `graph.js`, `nodeUtils.js`, inspector as needed  
**Done when**:

- [x] Toggle changes UI/handles
- [x] Illegal connections blocked in `isValidConnection`

**Tests**: none required (JS); manual checklist in SUMMARY  
**Status**: Done

---

### CTM-T5 — Actions modal `tool_exposure` (CTM-02)

**What**: Modal to edit slug, description, param labels; persist on node data; defaults from meta.  
**Where**: new `ToolExposureModal.jsx` (or equivalent), wire from Actions  
**Done when**:

- [x] Values round-trip in graph JSON
- [x] Empty slug gets default on blur/save

**Tests**: none required  
**Status**: Done

---

### CTM-T6 — `NodeAsTool` + ToolResolver `node:` (CTM-04)

**What**: Implement Tool wrapper; resolve `node:` ref; invoke `AgentRunner` with specialist config; nest `parent_run_id`.  
**Where**: `src/Runtime/Tools/NodeAsTool.php` (or `AgentAsTool.php`), `ToolResolver.php`  
**Done when**:

- [x] `__invoke` returns specialist content string
- [x] Fake provider test covers happy path + error string

**Tests**: unit `NodeAsToolTest` / resolver test  
**Status**: Done

---

### CTM-T7 — AgentNodeExecutor merge toolset + definition (CTM-04)

**What**: Supervisor visit merges AgentDefinition tools + canvas bindings (tool/mcp/node); show tools handle path for existing.  
**Where**: `AgentNodeExecutor.php`  
**Done when**:

- [x] Existing supervisor with toolset edge runs NodeAsTool
- [x] Inline supervisor still gets tool/mcp + toolset

**Tests**: feature `AgentNodeExecutor` / workflow interpreted test  
**Status**: Done

---

### CTM-T8 — Codegen snapshot tools (CTM-05)

**What**: Skip tool_mode nodes as workflow Nodes; snapshot specialist into supervisor generated tools.  
**Where**: `GraphTranspiler.php`, `AgentNodeCodeGenerator.php`, exporter  
**Done when**:

- [ ] Export has no specialist linear node
- [ ] Generated PHP constructs equivalent Tool

**Tests**: codegen unit/feature if pattern exists  
**Status**: Pending

---

### CTM-T9 — Docs (CTM-05) [P]

**What**: Document Tool Mode, toolable meta, wiring.  
**Where**: `docs/guides/workflows/node-types/ai-nodes.md`, `canvas-editor.md`, `docs/extending/custom-node-types.md`  
**Done when**:

- [ ] Sections live with mermaid for toolset→tools

**Tests**: none  
**Status**: Pending

---

### CTM-T10 — Template supervisor + specialist (CTM-05)

**What**: Add installable template demonstrating Tool Mode.  
**Where**: templates registry / JSON under package templates  
**Done when**:

- [ ] Template installs; harness run shows tool delegation (Fake or documented manual)

**Tests**: template install smoke if existing  
**Status**: Pending

---

## Requirement mapping

| ID | Tasks |
| ---- | ----- |
| CTM-01 | T1, T2, T4 |
| CTM-02 | T5 |
| CTM-03 | T2, T3, T4 |
| CTM-04 | T3, T6, T7 |
| CTM-05 | T8, T9, T10 |

**Coverage:** 5/5 mapped
