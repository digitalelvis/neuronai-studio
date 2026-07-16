# M5 Analítica e Faturamento — Task Index

**Context**: [context.md](./context.md)  
**Status**: Partial — CE + UA done; UE **ready** (Execute on `v0.6.x`)
**Linha**: CE `v0.4.0` · UA `v0.5.0` · UE → `v0.6.x` (AD-018)

## Feature order

| # | Feature | Tasks | Count | Status |
|---|---------|-------|------:|--------|
| 1 | [`cost-estimation`](../cost-estimation/tasks.md) | CE-T1…T13 | 13 | ✅ done |
| 2 | [`usage-export-api`](../usage-export-api/tasks.md) | UE-T1…T7 | 7 | ready (`v0.6.x`) |
| 3 | [`usage-analytics`](../usage-analytics/tasks.md) | UA-T1…T11 | 11 | ✅ done |

**Total**: 31 atomic tasks (24 done, 7 ready on `v0.6.x`)
**Note**: UA-T7 extracts `UsageQuery::aggregate` only; full UE HTTP is the remaining Execute slice.

## Cross-feature critical path

```mermaid
flowchart LR
    CE[CE done] --> UE[UE ready on v0.6.x]
    CE --> UA_api[UA-T1 JSON]
    CE --> UA_sse[UA-T5 SSE steps]
    CE --> UA_q[UA-T7 UsageQuery]
    UA_api --> UA_dbg[UA-T3..T4 Debugger]
    UA_sse --> UA_pretty[UA-T6 Pretty]
    UA_q --> UA_dash[UA-T8..T9 Dashboard]
    UA_dbg --> UA_build[UA-T10 bundles]
    UA_pretty --> UA_build
    UA_dash --> UA_docs[UA-T11 docs]
    UA_build --> UA_docs
```

## Parallelism notes

- UA no longer waits on full UE: Dashboard uses `UsageQuery::aggregate` (UA-T7).
- Pretty (T5→T6) and Debugger (T1→T4) and Dashboard (T7→T9) can proceed in parallel after CE.
- Docs/bundles after UI surfaces land.

## Remaining Execute slice

UA completed: **T1∥T2∥T5∥T7** → Debugger (T3–T4) ∥ Pretty (T6) ∥ Dashboard (T8–T9) → T10 bundles → T11 docs.
UE Execute on `v0.6.x` (AD-018); UA-T7 already provides Dashboard aggregates.
