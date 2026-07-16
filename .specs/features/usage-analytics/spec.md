# Usage Analytics Specification

## Problem Statement

Token usage and estimated cost are already persisted on runs/spans (CE), but the Studio UI rarely surfaces them. Operators see resource counts on the Dashboard and step latency in the Test Pretty view, without spend signal. M5 scope is **host-first**; Studio analytics stay **minimal** — no dedicated Usage page or BI charts.

## Goals

- [x] Show recent/window token totals (and estimated cost) on the existing Livewire Dashboard.
- [x] Show token (+ cost) badges on Neuron Debugger list/detail/timeline.
- [x] Show token (+ cost) on the Test harness **Pretty** view (Completed header + agent/llm step chips next to duration).
- [x] Keep UX lightweight (stats + badges) — not a new analytics product surface.

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Dedicated `/usage` page, charts, advanced filters | Deferred to M6 / future (context 1C) |
| Multi-tenant analytics | Host concern |
| Export CSV from Studio UI | Host uses `usage-export-api` |
| Real-time live updating graphs | Overkill for minimal surface |
| Full `usage-export-api` HTTP surface | Remains M5 debt; UA extracts only `UsageQuery::aggregate` |

**Context:** [.specs/features/m5-analytics-billing/context.md](../m5-analytics-billing/context.md)  
**Depends on:** [`cost-estimation`](../cost-estimation/spec.md) (done)

---

## User Stories

### P1: Dashboard usage summary cards ⭐ MVP

**User Story**: As a Studio operator, I want to see total tokens and estimated cost for a recent time window on the Dashboard so that I know whether usage is climbing without leaving the landing page.

**Why P1**: Cheapest Studio signal that M5 is visible internally; reuses existing Livewire Dashboard (decision 2A).

**Acceptance Criteria**:

1. WHEN the Dashboard loads THEN system SHALL show aggregate `total_tokens` (and prompt/completion if space allows) for a fixed recent window (**30 days**).
2. WHEN cost columns exist THEN the Dashboard SHALL show `estimated_cost` + currency for the same window.
3. WHEN there are no runs in the window THEN cards SHALL show zeros (not error).
4. WHEN layout already has resource count cards THEN usage cards SHALL sit alongside them without introducing a new navigation item.
5. WHEN aggregating the window THEN system SHALL exclude child runs (`parent_run_id IS NOT NULL`) to avoid double-counting nested agent usage.

**Independent Test**: Seed top-level runs with known tokens/cost → open `/neuronai-studio` → numbers match query.

---

### P1: Debugger token badges ⭐ MVP

**User Story**: As a developer debugging a run, I want to see token usage on the trace list and detail views so that I do not need raw JSON to spot expensive steps.

**Why P1**: Tokens already returned by `WorkflowTraceController`; UI gap called out since M4.

**Acceptance Criteria**:

1. WHEN the Debugger list renders a run THEN it SHALL display total tokens for that run (compact badge or secondary text).
2. WHEN the Debugger detail header renders THEN it SHALL display prompt / completion / total tokens.
3. WHEN a step/span is an LLM span with tokens THEN the timeline or step detail SHALL surface those token counts.
4. WHEN tokens are 0 THEN UI SHALL still show `0`.

**Independent Test**: Run workflow with LLM → open Debugger → badges match DB/JSON.

---

### P1: Test Pretty usage chips ⭐ MVP

**User Story**: As a developer running a workflow or agent in the Test tab, I want to see tokens and estimated cost in the Pretty view (next to step duration and on the Completed message) so that spend is visible without switching to Trace/JSON.

**Why P1**: Primary interactive surface; operators already look at Pretty for latency (`1706ms`) and miss usage today.

**Acceptance Criteria**:

1. WHEN a workflow run completes THEN the assistant **Completed** header (or adjacent meta) SHALL show run-level `total_tokens` and `estimated_cost` + currency.
2. WHEN Pretty renders an **agent** or **llm** content step THEN the step row SHALL show token counts (and estimated cost when > 0) beside duration when usage is available for that node.
3. WHEN usage is zero/unpriced THEN UI SHALL show `0` tokens and `0.00` cost (same convention as Debugger).
4. WHEN an agent playground run completes (non-workflow) THEN Pretty/Completed SHALL show that run’s tokens + estimated cost.
5. WHEN usage fields are missing (older payloads) THEN Pretty SHALL omit chips without breaking the thread.

**Independent Test**: Run the workflow from the screenshot path (agent node) → Pretty shows tokens/cost on Completed and on `agent_1` next to duration; values match the parent/child run after finalize.

---

### P2: Estimated cost on Debugger detail

**User Story**: As a developer, I want estimated cost on the run detail header so that I can correlate latency/errors with spend in one place.

**Why P2**: Nice polish; included in MVP when CE columns exist.

**Acceptance Criteria**:

1. WHEN cost can be computed for the run THEN detail header SHALL show estimated cost + currency.
2. WHEN cost is zero/unpriced THEN UI SHALL show `0.00` + currency code.

---

### P3: Recent runs token column

**User Story**: As an operator, I want a tokens column on the Dashboard recent-runs table so that expensive runs jump out.

**Why P3**: Included in MVP (trivial once models expose fields).

**Acceptance Criteria**:

1. WHEN recent runs table renders THEN each row SHALL show `total_tokens` (and estimated cost).

---

## Edge Cases

- WHEN JSON/SSE returns tokens but frontend cache/bundle is stale THEN republish assets / hard refresh shows correct badges.
- WHEN agent runs appear in Dashboard recent list THEN token fields SHALL display the same way as workflow runs (unified `StudioRun`).
- WHEN nested agent nodes roll usage to the parent THEN Pretty run-level totals SHALL match finalized parent run (own + children); step chips SHALL reflect that node’s child/span usage, not the full parent total.
- WHEN `usage-export-api` is still debt THEN Dashboard SHALL use a shared `UsageQuery::aggregate` extracted for UA (full export HTTP remains deferred).

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| UA-01 | P1: Dashboard usage summary | Execute | Complete |
| UA-02 | P1: Debugger token badges | Execute | Complete |
| UA-03 | P2: Cost on Debugger detail | Execute | Complete |
| UA-04 | P3: Recent runs token column | Execute | Complete |
| UA-05 | P1: Test Pretty usage chips | Execute | Complete |

**Coverage:** 5 total, mapped in [tasks.md](./tasks.md)

---

## Success Criteria

- [x] Dashboard shows window token (+ cost) totals without a new nav item.
- [x] Debugger list/detail/timeline show token (+ cost) badges.
- [x] Test Pretty shows run-level and agent/llm step usage chips.
- [x] Docs: `guides/dashboard.md` + `guides/analytics/usage.md` + harness/runtime notes.

---

## Dependencies

- **Requires:** `cost-estimation` (done).
- **Extracts:** minimal `UsageQuery::aggregate` (partial UE-T2) — does **not** require full export API.
- **Does not unblock:** full `usage-export-api` routes/controllers (remain debt).

## Documentation mapping

| Doc | Change |
| --- | ------ |
| `docs/guides/dashboard.md` | Usage cards / recent columns |
| `docs/guides/analytics/usage.md` | New — minimal Studio analytics + Pretty + link to export |
| `docs/guides/workflows/runtime-and-traces.md` | Debugger + Pretty usage notes |
| `docs/guides/agents/playground-and-threads.md` | Agent Completed usage chip |
