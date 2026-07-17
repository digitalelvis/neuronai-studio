# Agent Tool Controls — Tasks

**Design**: [design.md](./design.md) · **Spec**: [spec.md](./spec.md)  
**Status**: Ready — Execute on `v0.7.x`  
**Linha**: `v0.7.x` · **Ordem M6**: 1/3

---

## Execution Plan

```
ATC-T1 → ATC-T2 → ATC-T3 → ATC-T4
                └─→ ATC-T5 (UI)
ATC-T3 → ATC-T6 (codegen)
ATC-T4 → ATC-T7 (tests)
ATC-T5 → ATC-T8 (docs + bundles)
```

---

### ATC-T1 — Migration + model (ATC-01)

**What**: Add `tool_max_runs`, `parallel_tool_calls` to `agent_definitions`.  
**Where**: migration, `AgentDefinition`  
**Done when**:
- [ ] Columns nullable; model fillable/casts
**Tests**: via ATC-T7  
**Gate**: quick

---

### ATC-T2 — Apply knobs in AgentRunner (ATC-01)

**What**: Resolve definition + overrides; call Neuron `toolMaxRuns` / `parallelToolCalls`.  
**Where**: `AgentRunner`, `DynamicAgent` if needed, `AgentNodeExecutor`  
**Done when**:
- [ ] Overrides win; null → Neuron default
**Gate**: quick

---

### ATC-T3 — Live tool SSE in streamInline (ATC-02)

**What**: Map tool chunks to emitStep; dedupe vs post-history.  
**Where**: `AgentRunner::streamInline`, `AgentNodeExecutor`  
**Done when**:
- [ ] Tools appear before step_completed on stream path
**Gate**: quick

---

### ATC-T4 — Blocking path tool emit (ATC-02)

**What**: Ensure non-stream still emits tools before step_completed (history extract OK).  
**Done when**:
- [ ] No regression on existing tool SSE tests
**Gate**: quick

---

### ATC-T5 — UI editor + inspector (ATC-01)

**What**: Agent form fields + node override fields.  
**Done when**:
- [ ] Round-trip save/load
**Gate**: quick

---

### ATC-T6 — Codegen (ATC-03)

**What**: Emit knobs when set.  
**Done when**:
- [ ] Snapshot/string contains toolMaxRuns when configured
**Gate**: quick

---

### ATC-T7 — Tests (ATC-01, ATC-02)

**What**: Unit/feature for apply + live emit + dedupe + validation.  
**Where**: `tests/Runtime/AgentToolControlsTest.php`  
**Done when**:
- [ ] Green coverage of ACs
**Gate**: full suite subset

---

### ATC-T8 — Docs + assets (ATC-03)

**What**: Docs + rebuild bundles if UI changed.  
**Done when**:
- [ ] Docs listed in ROADMAP M6 index updated
**Gate**: docs
