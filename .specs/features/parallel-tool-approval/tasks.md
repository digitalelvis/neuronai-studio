# Parallel Tool Approval — Tasks

**Spec**: [spec.md](./spec.md) · **Context**: [../m8-performance-memory-context/context.md](../m8-performance-memory-context/context.md)  
**Status**: Specified (P2) — Execute on `v0.9.x` only after AMC + CTX  
**Linha**: `v0.9.x` · **Ordem M8**: 3/3  
**Design**: skipped — inline design decisions noted per task.

---

## Execution Plan

```
PTA-T1 → PTA-T2 → PTA-T3 → PTA-T4 → PTA-T5 → PTA-T6 → PTA-T7
```

Sequential by nature: exception shape → sequential catch → concurrent scheduler → runner/SSE → resume → parity tests → docs. No `[P]` tasks — shared mutable surface (fork runtime + checkpoint format).

---

### PTA-T1 — Extend `ParallelBranchInterruptException` (PTA-01)

**What**: Add tool-approval fields: reason (`human` | `tool_approval`), serialized approval payload (`WorkflowInterrupt` / tool calls), keeping existing human fields and completed-branch snapshots intact.  
**Where**: `src/Runtime/Exceptions/ParallelBranchInterruptException.php`  
**Depends on**: None  
**Reuses**: existing human-interrupt fields; serialization approach from AD-005 (class-based tools only)  
**Done when**:

- [ ] Exception round-trips serialize/restore with tool-approval payload
- [ ] Existing human-in-branch tests unaffected

**Tests**: unit  
**Gate**: quick

---

### PTA-T2 — Catch in `ForkNodeExecutor::runBranch` (sequential) (PTA-01)

**What**: Catch `ToolApprovalRequiredException` alongside `HumanInputRequiredException`; wrap with reason `tool_approval` + branch id + completed-branch snapshots.  
**Where**: `src/Runtime/NodeExecutors/ForkNodeExecutor.php`  
**Depends on**: PTA-T1  
**Reuses**: existing human-catch block (mirror, don't fork logic)  
**Done when**:

- [ ] Sequential fork with approval-gated agent raises `ParallelBranchInterruptException` (reason tool_approval), not a failed run

**Tests**: feature (two-branch fork, sequential config)  
**Gate**: quick

---

### PTA-T3 — Concurrent (Amp) scheduler handling (PTA-01)

**What**: On the Amp path, a branch's tool-approval interrupt must not cancel sibling futures: let running siblings finish, collect results into the interrupt snapshot, surface one interrupt deterministically.  
**Where**: concurrent branch scheduler (`ForkNodeExecutor` / `ParallelBranchRunner` Amp path from IPC)  
**Depends on**: PTA-T2  
**Reuses**: IPC concurrent fork/join machinery (`interpreted-parallel-concurrency`)  
**Inline design**: deterministic choice when multiple branches interrupt in the same tick = lowest branch order first (documented in code); siblings' spans/tokens still metered.  
**Done when**:

- [ ] Concurrent fork pauses instead of failing; sibling results captured
- [ ] Multiple simultaneous interrupts surface one at a time deterministically

**Tests**: feature (concurrent config; fake slow sibling)  
**Gate**: full suite subset

---

### PTA-T4 — Runner checkpoint + status + SSE (PTA-01)

**What**: `WorkflowRunner` persists checkpoint `kind: parallel` with the tool-approval interrupt, sets status `awaiting_tool_approval`, emits SSE `tool_approval_required` with branch id after sibling `branch_completed` events.  
**Where**: `src/Runtime/WorkflowRunner.php` (pause path), SSE payloads  
**Depends on**: PTA-T3  
**Reuses**: `pauseForToolApproval` (linear) + `kind: parallel` checkpoint from PE/L-003  
**Done when**:

- [ ] Run status/checkpoint verified in DB; SSE payload includes branch id + tool payload
- [ ] Harness (Test tab) shows the approval prompt (existing tool-approval UI, branch-aware label)

**Tests**: feature (SSE assertion)  
**Gate**: quick

---

### PTA-T5 — Resume approve/reject per branch (PTA-02)

**What**: `resumeToolApproval` handles parallel checkpoints: restore completed branches from snapshot, resume the pending branch (approve executes tool; reject follows existing rejection semantics), run not-yet-started branches from scratch (L-003), join merges all.  
**Where**: `WorkflowRunner::resumeToolApproval`, `ForkNodeExecutor` resume path  
**Depends on**: PTA-T4  
**Reuses**: L-003 resume iteration (human-in-branch); existing resume endpoints (sync stream + async job) — no new endpoint  
**Done when**:

- [ ] Approve: pending branch's tool runs; join output identical to a no-pause run
- [ ] Reject: rejected handle/feedback path taken; sibling results intact
- [ ] Human-shaped resume payload (`response`) against a tool-approval pause → validation error
- [ ] Unrestorable interrupt (Closure tool) → explicit error, branch not dropped silently

**Tests**: feature (approve, reject, invalid payload, async job resume)  
**Gate**: full suite subset

---

### PTA-T6 — Scheduler parity + multi-approval tests (PTA-03)

**What**: Cross-config test matrix: sequential vs concurrent equivalence (pauses, join output, persisted messages), multi-approval forks via successive pause/resume, mixed human + tool-approval fork, re-pause when a not-yet-started branch also requires approval.  
**Where**: `tests/Runtime/ParallelToolApprovalTest.php` (or split by scenario)  
**Depends on**: PTA-T5  
**Reuses**: existing parallel-execution test helpers  
**Done when**:

- [ ] Three-branch / two-approval scenario green under both `parallel.concurrency` values
- [ ] Mixed human + tool-approval fork green; checkpoints don't corrupt each other

**Tests**: feature (matrix)  
**Gate**: full

---

### PTA-T7 — Docs + codegen check (PTA-04)

**What**: Remove "unsupported" statements; document branch pause/resume; verify fork + approval codegen still compiles (document native-path behavior).  
**Where**: `docs/guides/workflows/node-types/logic-nodes.md`, `docs/guides/workflows/human-in-the-loop.md`, `docs/guides/workflows/runtime-and-traces.md`, `docs/reference/configuration.md` (if new keys); codegen snapshot  
**Depends on**: PTA-T6  
**Done when**:

- [ ] Docs rows from the ROADMAP M8 index updated; unsupported wording gone
- [ ] Codegen snapshot for fork + approval-gated agent compiles

**Tests**: codegen snapshot  
**Gate**: docs
