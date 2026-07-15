# Usage Analytics — Tasks

**Design**: [design.md](./design.md) · **Spec**: [spec.md](./spec.md)  
**Status**: Ready  
**Linha**: `v0.3.x` · **Ordem M5**: 3/3 (mínimo Studio)  
**Blocked by**: `UsageQuery` (UE-T2) + CE columns; Debugger tokens can start after CE-T3 if JSON already has tokens

---

## Execution Plan

```
UA-T1 → UA-T2
UE-T2 → UA-T3 → UA-T4
CE JSON fields → UA-T5 → UA-T6 → UA-T7
UA-T4 + UA-T7 → UA-T8
```

---

## Task Breakdown

### UA-T1 — Trace JSON: cost + provider/model (UA-02, UA-03)

**What**: Extend `WorkflowTraceController` list/detail payloads with `estimated_cost`, `currency`, and span `provider`/`model`/`estimated_cost`.  
**Where**: `src/Http/Controllers/WorkflowTraceController.php`, `tests/WorkflowTraceControllerTest.php`  
**Depends on**: CE-T3  
**Requirement**: UA-02, UA-03  

**Done when**:
- [ ] JSON includes new fields (0 defaults OK)
- [ ] Existing tests updated / green

**Tests**: feature `WorkflowTraceControllerTest`  
**Gate**: quick  

---

### UA-T2 — Debugger format helpers (UA-02)

**What**: Small `formatTokens` / `formatCost` utils in studio-traces.  
**Where**: `resources/js/studio-traces/` (e.g. `formatUsage.js`)  
**Depends on**: None ([P] with UA-T1)  
**Requirement**: UA-02  

**Done when**:
- [ ] Helpers exported and usable by list/detail components

**Tests**: none  
**Gate**: quick  

---

### UA-T3 — Debugger list + detail badges (UA-02, UA-03)

**What**: Token badge on `TraceListItem`; header on `TraceDetailViewer` with prompt/completion/total + estimated cost.  
**Where**: `resources/js/studio-traces/TraceListItem.jsx`, `TraceDetailViewer.jsx`  
**Depends on**: UA-T1, UA-T2  
**Requirement**: UA-02, UA-03  

**Done when**:
- [ ] List shows total tokens (including `0`)
- [ ] Detail shows tokens + cost + currency

**Tests**: none (manual / bundle build)  
**Gate**: quick  

---

### UA-T4 — Debugger timeline / step detail llm chips (UA-02)

**What**: Show tokens (and provider/model/cost when present) on timeline + step detail for llm spans.  
**Where**: `TraceStepTimeline.jsx`, `TraceStepDetail.jsx`  
**Depends on**: UA-T3  
**Requirement**: UA-02  

**Done when**:
- [ ] LLM steps surface token counts in UI

**Tests**: none  
**Gate**: quick  

---

### UA-T5 — Dashboard Livewire usage aggregates (UA-01)

**What**: Call `UsageQuery::aggregate` for last 30 days; pass totals + currency + label to view.  
**Where**: `src/Http/Livewire/Dashboard.php`  
**Depends on**: UE-T2  
**Requirement**: UA-01  

**Done when**:
- [ ] View data includes window totals
- [ ] Empty DB → zeros

**Tests**: feature `DashboardUsageTest` (or Livewire test)  
**Gate**: quick  

---

### UA-T6 — Dashboard blade cards + recent tokens column (UA-01, UA-04)

**What**: Stat cards for tokens + est. cost (30d); Tokens (+ cost) on recent runs table.  
**Where**: `resources/views/livewire/dashboard.blade.php`  
**Depends on**: UA-T5  
**Requirement**: UA-01, UA-04  

**Done when**:
- [ ] Cards visible beside existing resource counts
- [ ] No new nav item
- [ ] Recent rows show tokens

**Tests**: via UA-T5 assertion on HTML or Livewire  
**Gate**: quick  

---

### UA-T7 — Rebuild studio-traces frontend bundle (UA-02)

**What**: Run package Vite/npm build for studio-traces so published assets include badges.  
**Where**: package frontend build scripts / `dist` or public assets as repo convention  
**Depends on**: UA-T4  
**Requirement**: UA-02  

**Done when**:
- [ ] Built artifacts committed or CI-built per repo convention
- [ ] Debugger loads badges in browser smoke

**Tests**: none  
**Gate**: full (CI frontend build)  

---

### UA-T8 — Docs dashboard + usage guide (UA-01)

**What**: Update `guides/dashboard.md`; add `guides/analytics/usage.md`; note Debugger badges in runtime-and-traces.  
**Where**: `docs/guides/dashboard.md`, `docs/guides/analytics/usage.md`, `docs/guides/workflows/runtime-and-traces.md`  
**Depends on**: UA-T6, UA-T7  
**Requirement**: UA-01  

**Done when**:
- [ ] Docs describe minimal surface + link to export API

**Tests**: none  
**Gate**: quick  

---

## Dependency diagram ↔ tasks

| Task | Depends on | OK |
| ---- | ---------- | -- |
| UA-T1 | CE-T3 | ✓ |
| UA-T2 | — | ✓ |
| UA-T3 | T1, T2 | ✓ |
| UA-T4 | T3 | ✓ |
| UA-T5 | UE-T2 | ✓ |
| UA-T6 | T5 | ✓ |
| UA-T7 | T4 | ✓ |
| UA-T8 | T6, T7 | ✓ |

## Requirement traceability

| Req | Tasks |
| --- | ----- |
| UA-01 | T5, T6, T8 |
| UA-02 | T1, T2, T3, T4, T7 |
| UA-03 | T1, T3 |
| UA-04 | T6 |
