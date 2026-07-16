# Usage Export API — Tasks

**Design**: [design.md](./design.md) · **Spec**: [spec.md](./spec.md)  
**Status**: Ready — specs/design/tasks ready; Execute on `v0.6.x` (AD-018)  
**Linha**: `v0.6.x` · **Ordem M5**: 2/3  
 
**Blocked by**: `cost-estimation` CE-T12 (minimum: CE-T2..T5 + denormalized columns usable)

---

## Execution Plan

```
UE-T1 → UE-T2 → UE-T3 → UE-T4 → UE-T5
                └─→ UE-T6 (events, P2 — after T3)
UE-T5 → UE-T7
```

---

## Task Breakdown

### UE-T1 — Config `usage.export` / `usage.events` (UE-01, UE-03)

**What**: Complete `usage.export` (`enabled`, nullable `route_prefix`/`middleware` with fallback to `stream_adapters.*`) and `usage.events.enabled` default false. Merge with CE `usage` tree if stubs exist.  
**Where**: `config/neuronai-studio.php`  
**Depends on**: CE-T1 (same config tree)  
**Requirement**: UE-01, UE-03  

**Done when**:
- [ ] Export can be disabled independently of stream_adapters
- [ ] Null prefix/middleware fall back documented in code comments

**Tests**: via UE-T4  
**Gate**: quick  

---

### UE-T2 — `UsageQuery` (UE-01, UE-02, UE-04)

**What**: Aggregate + runDetail per design (exclude `parent_run_id` not null from window totals; `group_by=model|entity`; model filter on spans).  
**Where**: `src/Usage/UsageQuery.php`, `tests/Usage/UsageQueryTest.php`  
**Depends on**: UE-T1, CE-T3  
**Requirement**: UE-01, UE-02, UE-04  

**Done when**:
- [ ] Empty window → zero totals
- [ ] Children excluded from totals
- [ ] `group_by=model` returns breakdown from llm spans
- [ ] `runDetail` includes llm spans + entity + parent_run_id

**Tests**: unit/feature `UsageQueryTest`  
**Gate**: quick  

---

### UE-T3 — `UsageExportController` (UE-01, UE-02)

**What**: `index` + `showRun` with validation (`from`/`to`, 422 when invalid); JSON shapes from design.  
**Where**: `src/Http/Controllers/Integration/UsageExportController.php`  
**Depends on**: UE-T2  
**Requirement**: UE-01, UE-02  

**Done when**:
- [ ] 422 on from > to
- [ ] 404 missing run
- [ ] 200 + shape schema for happy path

**Tests**: via UE-T5  
**Gate**: quick  

---

### UE-T4 — Routes `routes/usage.php` + provider gate (UE-01)

**What**: New routes file; `registerRoutes` loads when `usage.export.enabled`; prefix/middleware resolution with fallback. Independent of `stream_adapters.enabled`.  
**Where**: `routes/usage.php`, `src/NeuronAIStudioServiceProvider.php`  
**Depends on**: UE-T1, UE-T3  
**Requirement**: UE-01  

**Done when**:
- [ ] enabled=true → routes registered
- [ ] enabled=false → 404 / absent
- [ ] stream_adapters=false + export=true still registers usage routes

**Tests**: `UsageExportRoutesTest`  
**Gate**: quick  

---

### UE-T5 — Feature tests export API (UE-01, UE-02, UE-04)

**What**: HTTP tests for aggregate, filters, group_by, per-run detail, validation.  
**Where**: `tests/Usage/UsageExportApiTest.php`, `tests/Usage/UsageExportRoutesTest.php`  
**Depends on**: UE-T4  
**Requirement**: UE-01, UE-02, UE-04  

**Done when**:
- [ ] Covers ACs from spec P1 (+ UE-04 filter)

**Tests**: feature  
**Gate**: full  

---

### UE-T6 — `RunUsageRecorded` event (UE-03) [P2]

**What**: Event class + dispatch on AgentRunner/WorkflowRunner terminal when `usage.events.enabled`.  
**Where**: `src/Events/RunUsageRecorded.php`, runners, `tests/Usage/RunUsageRecordedTest.php`  
**Depends on**: UE-T2, CE-T9  
**Requirement**: UE-03  

**Done when**:
- [ ] `Event::fake` asserts payload (tokens, cost, currency, entity, parent_run_id)
- [ ] Disabled flag → no dispatch

**Tests**: feature  
**Gate**: quick  

---

### UE-T7 — Docs export API (UE-01)

**What**: `guides/analytics/export-api.md`; config + installation notes.  
**Where**: `docs/guides/analytics/export-api.md`, `docs/reference/configuration.md`, `docs/getting-started/installation.md`  
**Depends on**: UE-T5  
**Requirement**: UE-01  

**Done when**:
- [ ] Examples for aggregate + per-run
- [ ] Middleware/auth is host-owned

**Tests**: none  
**Gate**: quick  

---

## Dependency diagram ↔ tasks

| Task | Depends on | OK |
| ---- | ---------- | -- |
| UE-T1 | CE-T1 | ✓ |
| UE-T2 | UE-T1, CE-T3 | ✓ |
| UE-T3 | UE-T2 | ✓ |
| UE-T4 | UE-T1, UE-T3 | ✓ |
| UE-T5 | UE-T4 | ✓ |
| UE-T6 | UE-T2, CE-T9 | ✓ |
| UE-T7 | UE-T5 | ✓ |

## Requirement traceability

| Req | Tasks |
| --- | ----- |
| UE-01 | T1, T2, T3, T4, T5, T7 |
| UE-02 | T2, T3, T5 |
| UE-03 | T1, T6 |
| UE-04 | T2, T5 |
