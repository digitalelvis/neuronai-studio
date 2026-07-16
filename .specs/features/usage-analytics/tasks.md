# Usage Analytics — Tasks

**Design**: [design.md](./design.md) · **Spec**: [spec.md](./spec.md)  
**Status**: Complete — UA-T1…T11 implemented 2026-07-16
**Linha**: shipped `v0.5.0` · **Ordem M5**: 3/3 (mínimo Studio + Pretty)  
 
**Blocked by**: CE done; extracts partial `UsageQuery` (does not need full UE)

---

## Execution Plan

```
UA-T1 ──→ UA-T2 ──→ UA-T3 ──→ UA-T4
  │                  │
  │                  └─→ UA-T5 (SSE + __steps) ──→ UA-T6 (Pretty UI)
  │
  └─→ UA-T7 (UsageQuery) ──→ UA-T8 ──→ UA-T9
UA-T4 + UA-T6 + UA-T9 ──→ UA-T10 (bundles) ──→ UA-T11 (docs)
```

---

## Task Breakdown

### UA-T1 — Trace JSON: cost + provider/model (UA-02, UA-03)

**What**: Extend `WorkflowTraceController` list/detail with `estimated_cost`, `currency`, and span `provider`/`model`/`estimated_cost`.  
**Where**: `src/Http/Controllers/WorkflowTraceController.php`, `tests/WorkflowTraceControllerTest.php`  
**Depends on**: CE (done)  
**Requirement**: UA-02, UA-03  

**Done when**:
- [x] JSON includes new fields (0 defaults OK)
- [x] Existing tests updated / green

**Tests**: feature `WorkflowTraceControllerTest`  
**Gate**: quick  

---

### UA-T2 — Shared format helpers (UA-02, UA-05)

**What**: `formatTokens` / `formatCost` in shared module for studio-traces and studio-chat.  
**Where**: `resources/js/lib/formatUsage.js` (or existing `lib/` convention)  
**Depends on**: None ([P] with UA-T1)  
**Requirement**: UA-02, UA-05  

**Done when**:
- [x] Helpers exported and importable from traces + chat

**Tests**: none  
**Gate**: quick  

---

### UA-T3 — Debugger list + detail badges (UA-02, UA-03)

**What**: Token badge on `TraceListItem`; header on `TraceDetailViewer` with prompt/completion/total + estimated cost. Fix sheet/page mappers that drop token fields.  
**Where**: `resources/js/studio-traces/TraceListItem.jsx`, `TraceDetailViewer.jsx`, `TraceDetailSheet.jsx`, Livewire trace-detail config if needed  
**Depends on**: UA-T1, UA-T2  
**Requirement**: UA-02, UA-03  

**Done when**:
- [x] List shows total tokens (including `0`)
- [x] Detail shows tokens + cost + currency

**Tests**: none (bundle / smoke)  
**Gate**: quick  

---

### UA-T4 — Debugger timeline / step detail llm chips (UA-02)

**What**: Show tokens (and provider/model/cost when present) on timeline + step detail for llm spans.  
**Where**: `TraceStepTimeline.jsx`, `TraceStepDetail.jsx`  
**Depends on**: UA-T3  
**Requirement**: UA-02  

**Done when**:
- [x] LLM steps surface token counts in UI

**Tests**: none  
**Gate**: quick  

---

### UA-T5 — SSE + `__steps` usage enrichment (UA-05)

**What**: Attach usage to agent/llm `__steps` entries and `step_completed` payloads; add run-level usage to `trace_completed` and agent playground `done`.  
**Where**: `GraphExecutionLoop` / node completion path, `AgentNodeExecutor`/`LlmNodeExecutor` (or post-step hook), `WorkflowStreamController`, `AgentChatStreamController`, tests  
**Depends on**: CE finalize (done)  
**Requirement**: UA-05  

**Done when**:
- [x] Agent node step carries child-run token/cost fields
- [x] LLM node step carries span token/cost fields
- [x] `trace_completed` includes finalized run usage + currency
- [x] Agent `done` includes run usage
- [x] Missing usage → fields omitted or zero without crash

**Tests**: feature stream / executor tests with FakeAIProvider Usage  
**Gate**: full  

---

### UA-T6 — Test Pretty UI chips (UA-05)

**What**: Propagate step/run usage into Pretty thread; show chips next to duration and on Completed header.  
**Where**: `resources/js/studio-chat/utils/workflowOutput.js`, `WorkflowThread.jsx`, `MessageList.jsx`, `StudioChat.jsx`  
**Depends on**: UA-T2, UA-T5  
**Requirement**: UA-05  

**Done when**:
- [x] Completed header shows run total tokens + est. cost
- [x] Agent/llm Pretty rows show tokens (+ cost) beside duration when present
- [x] Older payloads without usage still render

**Tests**: none (manual / bundle)  
**Gate**: quick  

---

### UA-T7 — `UsageQuery::aggregate` (UA-01)

**What**: Minimal query helper — window totals excluding `parent_run_id` not null; currency from config. No HTTP export.  
**Where**: `src/Usage/UsageQuery.php`, `tests/Usage/UsageQueryTest.php`  
**Depends on**: CE columns (done)  
**Requirement**: UA-01  

**Done when**:
- [x] Empty window → zeros
- [x] Children excluded from totals
- [x] Unit/feature tests green

**Tests**: `UsageQueryTest`  
**Gate**: quick  

**Notes**: Partial UE-T2; full `group_by` / `runDetail` / export routes remain UE debt.  

---

### UA-T8 — Dashboard Livewire usage aggregates (UA-01)

**What**: Call `UsageQuery::aggregate` for last 30 days; pass totals + currency + label to view.  
**Where**: `src/Http/Livewire/Dashboard.php`  
**Depends on**: UA-T7  
**Requirement**: UA-01  

**Done when**:
- [x] View data includes window totals
- [x] Empty DB → zeros

**Tests**: feature `DashboardUsageTest` (or Livewire)  
**Gate**: quick  

---

### UA-T9 — Dashboard blade cards + recent tokens column (UA-01, UA-04)

**What**: Stat cards for tokens + est. cost (30d); Tokens + cost on recent runs table.  
**Where**: `resources/views/livewire/dashboard.blade.php`  
**Depends on**: UA-T8  
**Requirement**: UA-01, UA-04  

**Done when**:
- [x] Cards visible beside existing resource counts
- [x] No new nav item
- [x] Recent rows show tokens (+ cost)

**Tests**: via UA-T8  
**Gate**: quick  

---

### UA-T10 — Rebuild frontend bundles (UA-02, UA-05)

**What**: Build chat + forms + canvas so Pretty and Debugger badges ship in published assets.  
**Where**: `package.json` / `resources/js/dist` per repo convention  
**Depends on**: UA-T4, UA-T6  
**Requirement**: UA-02, UA-05  

**Done when**:
- [x] Built artifacts generated per convention
- [x] Pretty + Debugger bundles compile successfully

**Tests**: none  
**Gate**: full (CI frontend build)  

---

### UA-T11 — Docs dashboard + usage + Pretty (UA-01, UA-05)

**What**: Update dashboard + runtime guides; add `guides/analytics/usage.md`; note Pretty chips and agent playground.  
**Where**: `docs/guides/dashboard.md`, `docs/guides/analytics/usage.md`, `docs/guides/workflows/runtime-and-traces.md`, `docs/guides/agents/playground-and-threads.md`, `docs/SUMMARY.md`  
**Depends on**: UA-T9, UA-T10  
**Requirement**: UA-01, UA-05  

**Done when**:
- [x] Docs describe Dashboard + Debugger + Pretty
- [x] Link to export API as host metering (still debt)

**Tests**: none  
**Gate**: quick  

---

## Dependency diagram ↔ tasks

| Task | Depends on | OK |
| ---- | ---------- | -- |
| UA-T1 | CE | ✓ |
| UA-T2 | — | ✓ |
| UA-T3 | T1, T2 | ✓ |
| UA-T4 | T3 | ✓ |
| UA-T5 | CE | ✓ |
| UA-T6 | T2, T5 | ✓ |
| UA-T7 | CE | ✓ |
| UA-T8 | T7 | ✓ |
| UA-T9 | T8 | ✓ |
| UA-T10 | T4, T6 | ✓ |
| UA-T11 | T9, T10 | ✓ |

## Requirement traceability

| Req | Tasks |
| --- | ----- |
| UA-01 | T7, T8, T9, T11 |
| UA-02 | T1, T2, T3, T4, T10 |
| UA-03 | T1, T3 |
| UA-04 | T9 |
| UA-05 | T2, T5, T6, T10, T11 |

## Parallelism notes

- After CE: **T1∥T2∥T5∥T7** can start in parallel.
- Pretty path (T5→T6) independent of Dashboard (T7→T9) until docs/bundles.
- Full UE HTTP remains debt; only `UsageQuery::aggregate` is pulled into UA-T7.
