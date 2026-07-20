# Parallel Tool Approval Specification

**Milestone:** M8 (P2 — execute after AMC and CTX) — [context](../m8-performance-memory-context/context.md) · **Tasks:** [tasks.md](./tasks.md)  
**Requirement IDs:** `PTA-xx` · **Date:** 2026-07-20

## Problem Statement

Tool approval works on the linear path (`WorkflowRunner::pauseForToolApproval` / `resumeToolApproval`, status `awaiting_tool_approval`, SSE `tool_approval_required` / `tool_approval_resolved`), but inside a fork it is fail-closed: `ForkNodeExecutor::runBranch` only catches `HumanInputRequiredException`, so a `ToolApprovalRequiredException` raised in a branch escapes — on the Amp concurrent path an uncaught throwable cancels the other futures and fails the whole run. Docs (`logic-nodes.md`, `human-in-the-loop.md`) declare the combination unsupported. Agents with `require_tool_approval` therefore cannot run inside parallel branches, breaking production autonomy under concurrency (M6/IPC known gap).

## Goals

- [ ] A tool-approval request inside a parallel branch pauses the run with a parallel checkpoint instead of failing it — in both sequential and Amp concurrent scheduling.
- [ ] Resume with approve/reject targets the pending branch, preserving completed-branch results and running not-yet-started branches (L-003 pattern).
- [ ] SSE and harness UX match the linear tool-approval experience, with branch identification.
- [ ] Docs no longer list tool approval in branches as unsupported.

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Changing linear (non-fork) tool approval | Already shipped (M2); reused as-is |
| Approving multiple pending branches in one resume call | One approval per resume, matching linear API; batch UX later |
| Per-tool approval policies / allowlists | Approval scope stays as today (agent-level `require_tool_approval`) |
| Human input + tool approval combined redesign | Human-in-branch keeps its existing path; only reason/fields are shared |
| Nested forks (fork inside a branch) | Not supported by the canvas today |

**Context:** [m8-performance-memory-context/context.md](../m8-performance-memory-context/context.md)

---

## User Stories

### P2: Pause on tool approval inside a branch (both schedulers)

**User Story**: As a workflow author, I want a run to pause — not fail — when an agent in a parallel branch requires tool approval, so that approval-gated agents work under concurrency.

**Why P2**: M8 P2 by AD-022 — specified now, executed after the two P1 features.

**Acceptance Criteria**:

1. WHEN an agent in a branch throws `ToolApprovalRequiredException` under **sequential** scheduling THEN `ForkNodeExecutor::runBranch` SHALL catch it and raise `ParallelBranchInterruptException` with reason `tool_approval`, carrying the branch id, the serialized approval payload (tool calls / `WorkflowInterrupt`), and completed-branch snapshots — mirroring the existing human-in-branch handling.
2. WHEN the same happens under **Amp concurrent** scheduling THEN the scheduler SHALL let already-running sibling branches finish (or capture their state deterministically), collect their results into the interrupt snapshot, and SHALL NOT fail the run.
3. WHEN the interrupt reaches `WorkflowRunner` THEN it SHALL persist a checkpoint with `kind: parallel` and set run status `awaiting_tool_approval` (same status string as linear).
4. WHEN the pause occurs THEN SSE SHALL emit `tool_approval_required` including the branch id and tool payload, after any `branch_completed` events for finished siblings.

**Independent Test**: Two-branch fork, branch B's agent requires approval → run pauses with `awaiting_tool_approval`, checkpoint `kind: parallel` holds branch A's completed snapshot; identical observable outcome with `parallel.concurrency=sequential` and `=concurrent`.

---

### P2: Resume approve/reject per branch

**User Story**: As an operator, I want to approve or reject the pending branch's tool call and have the run finish correctly, so that pausing is actually recoverable.

**Acceptance Criteria**:

1. WHEN resume is called with `approve` THEN the pending branch SHALL continue from its interrupt (tool executes), completed branches SHALL be restored from the checkpoint without re-running, and not-yet-started branches SHALL run from scratch (L-003), then join merges all branch results.
2. WHEN resume is called with `reject` THEN the pending branch SHALL follow the existing rejection semantics (rejected handle when wired, otherwise agent continues with rejection feedback), and the run SHALL proceed to join without losing sibling results.
3. WHEN resume targets a run whose pending interrupt is a parallel tool approval THEN the existing resume endpoints (`POST .../resume/stream` sync and `.../resume` async job) SHALL handle it — no new endpoint.
4. WHEN the resumed run completes THEN SSE SHALL emit `tool_approval_resolved` and join output SHALL be identical to a run where no pause occurred (given same tool results).

**Independent Test**: Pause from PTA-01 scenario → resume `approve` → branch B tool runs, join merges A + B; resume `reject` variant → rejected path taken, A's result intact.

---

### P2: Scheduler parity + multiple simultaneous approvals

**User Story**: As a workflow author, I want identical semantics regardless of `parallel.concurrency`, including when more than one branch needs approval, so that switching schedulers is safe.

**Acceptance Criteria**:

1. WHEN two branches both require tool approval in the same fork THEN system SHALL surface them deterministically one at a time (pause → resume → next pause), with each checkpoint accumulating previously completed/resolved branches; total resumes = number of approvals.
2. WHEN `parallel.concurrency=sequential` vs `concurrent` THEN the set of pauses, final join output, and persisted messages SHALL be equivalent (ordering of `branch_*` SSE events MAY differ).
3. WHEN a branch requires tool approval AND another requires human input in the same fork THEN each interrupt SHALL pause with its own reason and resume path, without corrupting the other's checkpoint data.

**Independent Test**: Three-branch fork where two branches need approval → two pause/resume cycles complete the run with all three branch results at join, under both scheduler configs.

---

### P2: Codegen + docs

**User Story**: As a host, I want docs and exported code to reflect that tool approval inside branches is supported.

**Acceptance Criteria**:

1. Docs SHALL update `guides/workflows/node-types/logic-nodes.md` and `guides/workflows/human-in-the-loop.md` removing the "unsupported" statements and documenting pause/resume-in-branch behavior, plus `guides/workflows/runtime-and-traces.md` (checkpoint/status) and `reference/configuration.md` if new keys appear.
2. WHEN codegen exports a workflow with fork + approval-gated agent THEN generated code SHALL keep compiling (native path behavior documented; no silent break).

---

## Edge Cases

- WHEN resume approves the pending branch but a not-yet-started branch then throws its own `ToolApprovalRequiredException` THEN system SHALL pause again with a new checkpoint that includes all branches completed so far (iterative L-003).
- WHEN the pending interrupt's serialized `WorkflowInterrupt` cannot be restored (e.g. Closure-based tool — known limitation from AD-005) THEN resume SHALL fail with an explicit error, not silently drop the branch.
- WHEN a run is paused for parallel tool approval and receives a resume payload shaped for human input (`response` instead of approve/reject) THEN system SHALL reject the request with a validation error.
- WHEN the checkpoint's completed-branch snapshot references state keys later mutated by the resumed branch THEN join SHALL use the snapshot values for completed branches (isolation invariant from AD-007 holds).
- WHEN sibling branches are still running on the Amp path at pause time THEN their tokens/spans SHALL still be metered and traced (no orphan spans).
- WHEN a fork with an approval pause is itself resumed after a worker restart (async job path) THEN checkpoint restore SHALL be sufficient — no in-memory scheduler state required.

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| PTA-01 | P2: Pause in branch (both schedulers) | Tasks | Pending |
| PTA-02 | P2: Resume approve/reject per branch | Tasks | Pending |
| PTA-03 | P2: Scheduler parity + multi-approval | Tasks | Pending |
| PTA-04 | P2: Codegen + docs | Tasks | Pending |

**Coverage:** 4 total, 4 mapped to tasks ([tasks.md](./tasks.md)), 0 unmapped

---

## Success Criteria

- [ ] Fork + approval-gated agent no longer fails the run: pauses with `awaiting_tool_approval` and a `kind: parallel` checkpoint, under both scheduler modes.
- [ ] Approve and reject resumes produce correct join output with completed branches preserved and unstarted branches executed.
- [ ] Multi-approval forks complete via successive pause/resume cycles.
- [ ] Docs updated; "unsupported" statements removed.
