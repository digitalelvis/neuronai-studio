# Usage Export API Specification

## Problem Statement

Hosts that embed NeuronAI Studio need to **bill or report** AI usage to their own customers. Today token data lives only in Studio DB and Debugger JSON under Studio auth — there is no host-facing aggregate API or event surface for metering.

## Goals

- [ ] Expose a configurable REST aggregate usage API under the integration route group (M4 pattern).
- [ ] Return tokens + estimated cost for time windows and entity filters (agent / workflow).
- [ ] Allow the host to disable the export surface via config.
- [ ] Document auth/middleware expectations (host-owned).

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Payment / invoicing / Stripe | Host owns billing; package only meters |
| Multi-tenant isolation schema | Host scopes via middleware / app queries |
| Real-time streaming of usage events as primary MVP | Optional P2 Laravel events; REST aggregate is MVP |
| CSV/webhook push delivery | Deferred |
| Full Studio usage BI | See minimal `usage-analytics` |

**Context:** [.specs/features/m5-analytics-billing/context.md](../m5-analytics-billing/context.md)  
**Depends on:** [`cost-estimation`](../cost-estimation/spec.md) for estimated cost fields

---

## User Stories

### P1: Aggregated usage REST endpoint ⭐ MVP

**User Story**: As a host app developer, I want an authenticated JSON API that returns usage aggregates for a time range so that I can meter customers or fill my billing pipeline.

**Why P1**: Core deliverable of M5 host-first scope.

**Acceptance Criteria**:

1. WHEN `usage.export.enabled` (or equivalent) is true THEN system SHALL register export routes under `stream_adapters.route_prefix` (default `api/neuronai`) with `stream_adapters.middleware` (host-configurable).
2. WHEN `GET .../usage` (final path design discretion) is called with `from` / `to` (ISO8601 or date) THEN system SHALL return aggregated `prompt_tokens`, `completion_tokens`, `total_tokens`, and `estimated_cost` (+ `currency`) for runs in that window.
3. WHEN optional filters `entity_type` + `entity_id` (agent/workflow) are provided THEN aggregates SHALL be scoped to that entity via `StudioThread` morph.
4. WHEN optional `group_by=model` (or `entity`) is provided THEN response SHALL include a breakdown array; without it, a single totals object is enough for MVP.
5. WHEN `usage.export.enabled` is false THEN routes SHALL NOT be registered (404).

**Independent Test**: Seed runs with tokens/cost → `GET /api/neuronai/usage?from=&to=` with test middleware returns expected totals.

---

### P1: Per-run usage detail for reconciliation ⭐ MVP

**User Story**: As a host developer, I want to fetch usage for a specific run so that I can attach cost to a customer transaction that maps 1:1 to a Studio run.

**Why P1**: Aggregation alone is hard to reconcile; run-level lookup is the billing primitive.

**Acceptance Criteria**:

1. WHEN `GET .../usage/runs/{run}` (path final in design) is called THEN system SHALL return that run's token totals, estimated cost, entity reference, timestamps, and status.
2. WHEN the run does not exist THEN system SHALL return 404.
3. WHEN the run has LLM spans THEN response MAY include a compact per-span breakdown (`provider`, `model`, tokens, estimated_cost) — include in MVP if cheap; otherwise P2.

**Independent Test**: Create known run → fetch by id → numbers match DB.

---

### P2: Laravel usage events

**User Story**: As a host developer, I want the package to dispatch a Laravel event when a run completes with usage payload so that I can write to my ledger without polling.

**Why P2**: Improves integration DX; REST remains sufficient for MVP.

**Acceptance Criteria**:

1. WHEN a run reaches a terminal status (`completed` / `failed`) THEN system SHALL dispatch a documented event containing run id, entity, tokens, estimated_cost.
2. WHEN export is disabled THEN events MAY still fire if `usage.events.enabled` is true (separate flag) — or follow single master `usage.enabled`; design picks one coherent config tree.

**Independent Test**: `Event::fake` → run agent → assert event payload.

---

### P3: Filter by model in aggregate

**User Story**: As a host developer, I want to filter aggregates by model string so that premium models can be priced differently downstream.

**Why P3**: Nice for advanced metering; group_by already covers much of this.

**Acceptance Criteria**:

1. WHEN `model` query param is set THEN aggregates SHALL include only spans (or runs that used) that model.

---

## Edge Cases

- WHEN `from` > `to` THEN system SHALL return 422 with validation error.
- WHEN window is empty THEN system SHALL return zeros (not 404).
- WHEN estimated cost is unavailable (unpriced models) THEN `estimated_cost` SHALL be 0 and tokens SHALL still return.
- WHEN huge windows cause slow queries THEN v1 documents recommended indexes / max window; pagination of run lists is out of scope for aggregate endpoint (detail endpoint is single run).

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| UE-01 | P1: Aggregated usage REST | Tasks | In Tasks |
| UE-02 | P1: Per-run usage detail | Tasks | In Tasks |
| UE-03 | P2: Laravel usage events | Tasks | In Tasks |
| UE-04 | P3: Filter by model | Tasks | In Tasks |

**Coverage:** 4 total, mapped in [tasks.md](./tasks.md)

---

## Success Criteria

- [ ] Host can enable middleware + call aggregate and per-run usage endpoints.
- [ ] Response includes tokens + estimated_cost + currency.
- [ ] Docs: `guides/analytics/export-api.md`, config + installation notes.
- [ ] Feature can be turned off without affecting Studio UI.

---

## Dependencies

- **Requires:** `cost-estimation` (CE-01..CE-03) for meaningful `estimated_cost`.
- **Related:** M4 `stream_adapters` route registration pattern.

## Documentation mapping

| Doc | Change |
| --- | ------ |
| `docs/guides/analytics/export-api.md` | New — endpoints, params, examples |
| `docs/reference/configuration.md` | `usage.export` / events flags |
| `docs/getting-started/installation.md` | Brief enable/middleware note |
