# Interpreted Parallel Concurrency — Tasks

**Design**: [design.md](./design.md) · **Spec**: [spec.md](./spec.md)  
**Status**: Ready — Execute on `v0.7.x`  
**Linha**: `v0.7.x` · **Ordem M6**: 3/3  
**Blocked by**: prefer ARP-T2 (SerializingEmitter can wrap ProgressEmitter)

---

## Execution Plan

```
IPC-T1 → IPC-T2 → IPC-T3 → IPC-T4 → IPC-T5
```

---

### IPC-T1 — Config `parallel.concurrency` (IPC-03)

**What**: Add config key + helper `ParallelRuntime::mode()`.  
**Done when**:
- [ ] sequential|concurrent validated
**Gate**: quick

---

### IPC-T2 — SerializingEmitter (IPC-01)

**What**: Thread-safe wrapper for stepEmitter.  
**Done when**:
- [ ] Unit test concurrent appends produce valid sequential calls
**Gate**: quick

---

### IPC-T3 — ConcurrentBranchScheduler (IPC-01)

**What**: Amp await of branch callables; fail-fast + interrupt propagation.  
**Done when**:
- [ ] Dual sleep bench faster than sequential
**Gate**: quick

---

### IPC-T4 — ForkNodeExecutor integration (IPC-01, IPC-02)

**What**: Wire scheduler; keep resume semantics.  
**Done when**:
- [ ] Existing parallel resume tests pass concurrent + sequential
**Gate**: full

---

### IPC-T5 — Docs (IPC-03)

**What**: logic-nodes, runtime-and-traces, configuration.  
**Done when**:
- [ ] Docs note tool-approval-in-branch still unsupported
**Gate**: docs
