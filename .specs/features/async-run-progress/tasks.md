# Async Run Progress — Tasks

**Design**: [design.md](./design.md) · **Spec**: [spec.md](./spec.md)  
**Status**: Ready — Execute on `v0.7.x`  
**Linha**: `v0.7.x` · **Ordem M6**: 2/3  
**Blocked by**: none (can start after ATC preferred)

---

## Execution Plan

```
ARP-T1 → ARP-T2 → ARP-T3 → ARP-T4 → ARP-T5
ARP-T2 → ARP-T6 (docs)
```

---

### ARP-T1 — Config `async_progress` (ARP-03)

**What**: `enabled`, `ttl`, `poll_ms` in config.  
**Done when**:
- [ ] Defaults documented
**Gate**: quick

---

### ARP-T2 — ProgressBuffer + ProgressEmitter (ARP-01)

**What**: Append/readAfter/clear; emitter callable.  
**Where**: `src/Runtime/Progress/*`  
**Done when**:
- [ ] Unit tests for seq ordering
**Gate**: quick

---

### ARP-T3 — Wire jobs (ARP-01)

**What**: Pass emitter when enabled; terminal event on complete/fail.  
**Where**: `RunWorkflowJob`, `ResumeWorkflowJob`, possibly `WorkflowRunner` finalize hooks  
**Done when**:
- [ ] Buffer populated during job handle
**Gate**: quick

---

### ARP-T4 — Incremental flush (ARP-01)

**What**: Persist steps/spans on step_completed when possible.  
**Done when**:
- [ ] Polling JSON shows steps before job end in test
**Gate**: quick

---

### ARP-T5 — SSE tail controller + route (ARP-02)

**What**: StreamedResponse + tests.  
**Done when**:
- [ ] Events streamed; closes on terminal
**Gate**: full

---

### ARP-T6 — Docs (ARP-03)

**What**: runtime-and-traces, configuration, export-and-production note.  
**Done when**:
- [ ] Docs updated
**Gate**: docs
