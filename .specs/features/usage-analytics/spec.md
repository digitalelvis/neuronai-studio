# Usage Analytics Specification

## Problem Statement

Token usage is already in Debugger JSON and the DB, but the Studio UI never shows it. Operators opening the Dashboard only see resource counts and recent runs without spend signal. M5 scope is **host-first**; Studio analytics stay **minimal** — no dedicated Usage page or BI charts.

## Goals

- [ ] Show recent/window token totals (and estimated cost when available) on the existing Livewire Dashboard.
- [ ] Show token badges on Neuron Debugger list/detail (data already in JSON).
- [ ] Keep UX lightweight (stats + badges) — not a new analytics product surface.

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Dedicated `/usage` page, charts, advanced filters | Deferred to M6 / future (context 1C) |
| Multi-tenant analytics | Host concern |
| Export CSV from Studio UI | Host uses `usage-export-api` |
| Real-time live updating graphs | Overkill for minimal surface |

**Context:** [.specs/features/m5-analytics-billing/context.md](../m5-analytics-billing/context.md)  
**Depends on:** [`cost-estimation`](../cost-estimation/spec.md) for cost figures (tokens alone are enough for Debugger badges)

---

## User Stories

### P1: Dashboard usage summary cards ⭐ MVP

**User Story**: As a Studio operator, I want to see total tokens and estimated cost for a recent time window on the Dashboard so that I know whether usage is climbing without leaving the landing page.

**Why P1**: Cheapest Studio signal that M5 is visible internally; reuses existing Livewire Dashboard (decision 2A).

**Acceptance Criteria**:

1. WHEN the Dashboard loads THEN system SHALL show aggregate `total_tokens` (and prompt/completion if space allows) for a fixed recent window (default 7 or 30 days — agent discretion).
2. WHEN `cost-estimation` is available THEN the Dashboard SHALL show `estimated_cost` + currency for the same window.
3. WHEN there are no runs in the window THEN cards SHALL show zeros (not error).
4. WHEN layout already has resource count cards THEN usage cards SHALL sit alongside them without introducing a new navigation item.

**Independent Test**: Seed runs with known tokens/cost → open `/neuronai-studio` → numbers match query.

---

### P1: Debugger token badges ⭐ MVP

**User Story**: As a developer debugging a run, I want to see token usage on the trace list and detail views so that I do not need raw JSON to spot expensive steps.

**Why P1**: Data already returned by `WorkflowTraceController`; UI gap called out since M4.

**Acceptance Criteria**:

1. WHEN the Debugger list renders a run THEN it SHALL display total tokens for that run (compact badge or secondary text).
2. WHEN the Debugger detail header renders THEN it SHALL display prompt / completion / total tokens.
3. WHEN a step/span is an LLM span with tokens THEN the timeline or step detail SHALL surface those token counts.
4. WHEN tokens are 0 THEN UI SHALL still show `0` (or omit badge — prefer show `0` for consistency).

**Independent Test**: Run workflow with LLM → open Debugger → badges match DB/JSON.

---

### P2: Estimated cost on Debugger detail

**User Story**: As a developer, I want estimated cost on the run detail header so that I can correlate latency/errors with spend in one place.

**Why P2**: Nice polish; aggregate Dashboard already covers ops glance.

**Acceptance Criteria**:

1. WHEN cost can be computed for the run THEN detail header SHALL show estimated cost + currency.
2. WHEN cost is zero/unpriced THEN UI SHALL show `$0.00` / `0` or hide cost line consistently (pick one in design).

---

### P3: Recent runs token column

**User Story**: As an operator, I want a tokens column on the Dashboard recent-runs table so that expensive runs jump out.

**Why P3**: Optional density; badges in Debugger already cover deep dive.

**Acceptance Criteria**:

1. WHEN recent runs table renders THEN each row MAY show `total_tokens` (and optional cost).

---

## Edge Cases

- WHEN JSON API returns tokens but frontend cache is stale THEN hard refresh shows correct badges (no special realtime bus).
- WHEN agent runs appear in Dashboard recent list THEN token fields SHALL display the same way as workflow runs (unified `StudioRun`).
- WHEN cost-estimation is not yet deployed but analytics UI ships THEN Dashboard SHALL show tokens only and hide or zero cost until CE lands (implement CE first in task order).

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| UA-01 | P1: Dashboard usage summary | Tasks | In Tasks |
| UA-02 | P1: Debugger token badges | Tasks | In Tasks |
| UA-03 | P2: Cost on Debugger detail | Tasks | In Tasks |
| UA-04 | P3: Recent runs token column | Tasks | In Tasks |

**Coverage:** 4 total, mapped in [tasks.md](./tasks.md)

---

## Success Criteria

- [ ] Dashboard shows window token (+ cost) totals without a new nav item.
- [ ] Debugger list/detail show token badges.
- [ ] Docs: `guides/dashboard.md` updated; `guides/analytics/usage.md` describes the minimal Studio surface (points to export API for host metering).

---

## Dependencies

- **Requires:** tokens on runs (done); `cost-estimation` for cost cards (order after CE).
- **Does not block:** `usage-export-api` (can ship in parallel after CE).

## Documentation mapping

| Doc | Change |
| --- | ------ |
| `docs/guides/dashboard.md` | Usage cards / recent columns |
| `docs/guides/analytics/usage.md` | New — minimal Studio analytics + link to export |
| `docs/guides/workflows/runtime-and-traces.md` | Debugger token badges note |
