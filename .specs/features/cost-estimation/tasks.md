# Cost Estimation — Tasks

**Design**: [design.md](./design.md) · **Spec**: [spec.md](./spec.md)  
**Status**: Ready  
**Linha**: `v0.3.x` · **Ordem M5**: 1/3 (bloqueia UE + UA)

---

## Execution Plan

### Phase 1 — Schema & pricing (parallel after T1 config optional)

```
T1 ──┬─→ T2 → T3
     └─→ T4
T3 + T4 → T5 → T6
```

### Phase 2 — Runtime wiring

```
T6 ──┬─→ T7 ─┬─→ T9
     ├─→ T8 ─┤
     ├─→ T10 ┘
     └─→ T11
T7 + T10 + T11 → T12
```

### Phase 3 — Docs

```
T12 → T13
```

---

## Task Breakdown

### CE-T1 — Config `usage` (currency + pricing seeds) (CE-02, CE-04)

**What**: Add `usage.currency` and `usage.pricing` map with approximate defaults for catalog models (openai/anthropic/gemini/ollama). Leave `export`/`events` keys as stubs or omit until UE-T1 (prefer stubs with defaults matching design so publish is stable).  
**Where**: `config/neuronai-studio.php`  
**Depends on**: None  
**Requirement**: CE-02, CE-04  

**Done when**:
- [x] `config('neuronai-studio.usage.currency')` defaults to `USD`
- [x] At least one price entry per provider catalog model family used in defaults (`gpt-4o-mini`, etc.)
- [x] Ollama models priced at `0`

**Status**: ✅ Done  
**Notes**: Rates are USD **per 1k** tokens (sticker $/1M ÷ 1000). Design sample used sticker $/1M in `prompt_per_1k` fields — corrected per product decision. Stubs `usage.export` / `usage.events` included for UE stability.  

**Tests**: none (covered by CE-T4)  
**Gate**: quick  

---

### CE-T2 — Migration usage columns (CE-01, CE-03)

**What**: New migration adding `provider`, `model`, `estimated_cost` on `trace_spans`; `estimated_cost`, `parent_run_id` on `runs`; indexes as in design.  
**Where**: `database/migrations/*_add_usage_cost_columns_to_runs_and_spans.php`  
**Depends on**: None ([P] with CE-T1)  
**Requirement**: CE-01, CE-03  

**Done when**:
- [x] Migration uses `StudioTables::name()`
- [x] `parent_run_id` FK nullOnDelete → runs
- [x] `estimated_cost` is `decimal(12,6)` default 0

**Status**: ✅ Done  
**Notes**: Also adds indexes on `runs(parent_run_id)` and `runs(started_at)`. `down()` drops FK then indexes before columns (SQLite-safe).  

**Tests**: `MigrationTest` extended or run existing migration suite  
**Gate**: quick  

**Gate check**: `./vendor/bin/phpunit tests/MigrationTest.php` — OK (3 tests, 24 assertions)  

---

### CE-T3 — Models fillable / casts / relations (CE-01, CE-03)

**What**: Update `StudioRun` / `StudioTraceSpan` for new columns; `parent()` / `children()` on run.  
**Where**: `src/Models/StudioRun.php`, `src/Models/StudioTraceSpan.php`  
**Depends on**: CE-T2  
**Requirement**: CE-01, CE-03  

**Done when**:
- [x] Fillable + casts (`estimated_cost` → `decimal:6`)
- [x] Relations work in a unit/feature smoke

**Status**: ✅ Done  

**Tests**: covered by CE-T12  
**Gate**: quick  

**Gate check**: `./vendor/bin/phpunit tests/MigrationTest.php` — OK (4 tests, 31 assertions)  

---

### CE-T4 — `UsageCostEstimator` [P] (CE-02, CE-03)

**What**: Pure estimator class + unit tests (priced, unpriced, zero tokens, bad rates → 0).  
**Where**: `src/Usage/UsageCostEstimator.php`, `tests/Usage/UsageCostEstimatorTest.php`  
**Depends on**: CE-T1  
**Requirement**: CE-02, CE-03  

**Done when**:
- [x] Formula `(p/1000)*rate_p + (c/1000)*rate_c`
- [x] Missing key → `"0.000000"` (or equivalent decimal string/float consistent with casts)
- [x] Unit tests green

**Status**: ✅ Done  

**Tests**: unit `UsageCostEstimatorTest`  
**Gate**: quick  

**Gate check**: `./vendor/bin/phpunit tests/Usage/UsageCostEstimatorTest.php` — OK (10 tests, 15 assertions)  

---

### CE-T5 — `UsageRecorder` (CE-01, CE-03)

**What**: Persist LLM span + increment run (and optional parent) tokens/cost. Shared by tracker and LlmNodeExecutor.  
**Where**: `src/Usage/UsageRecorder.php`, `tests/Usage/UsageRecorderTest.php`  
**Depends on**: CE-T3, CE-T4  
**Requirement**: CE-01, CE-03  

**Done when**:
- [x] `recordLlmSpan(...)` writes provider/model/tokens/estimated_cost
- [x] Increments child run; increments parent when provided
- [x] Never throws on null usage / missing price

**Status**: ✅ Done  

**Tests**: unit/feature `UsageRecorderTest`  
**Gate**: quick  

**Gate check**: `./vendor/bin/phpunit tests/Usage/UsageRecorderTest.php` — OK (4 tests, 24 assertions)  

---

### CE-T6 — Extend `TelemetryTracker` (CE-01, CE-03)

**What**: Ctor accepts provider/model/parentRun; inference-stop delegates to `UsageRecorder`.  
**Where**: `src/Runtime/TelemetryTracker.php`  
**Depends on**: CE-T5  
**Requirement**: CE-01, CE-03  

**Done when**:
- [ ] Existing observe path still creates llm span
- [ ] Span has provider/model/cost when ctor provided
- [ ] Parent incremented when set

**Tests**: via CE-T12 / extend `AgentRunnerTest` as needed  
**Gate**: quick  

---

### CE-T7 — `AgentRunner` meter wiring + `parent_run_id` (CE-01)

**What**: Pass provider/model into tracker; accept optional parent run; set `parent_run_id` on created run; wire all inline/resume/structured sites.  
**Where**: `src/Runtime/AgentRunner.php`  
**Depends on**: CE-T6  
**Requirement**: CE-01  

**Done when**:
- [ ] Inline paths pass config provider/model
- [ ] Optional parent → `parent_run_id` persisted + tracker parent set

**Tests**: feature (CE-T12)  
**Gate**: quick  

---

### CE-T8 — `AgentRunner::stream` / `streamHandler` observe (CE-01)

**What**: Create/reuse execution session and attach `TelemetryTracker` so playground + integrate streams meter.  
**Where**: `src/Runtime/AgentRunner.php`  
**Depends on**: CE-T7  
**Requirement**: CE-01  

**Done when**:
- [ ] After `stream`/`streamHandler` run, llm spans exist with tokens when provider returns usage
- [ ] Playground/integrate behavior otherwise unchanged (regession: existing stream tests)

**Tests**: extend `AgentRunnerPlaygroundTest` / `AgentIntegrateStreamTest` lightly or CE-T12  
**Gate**: full  

---

### CE-T9 — Run finalize = own spans + children (CE-03)

**What**: On terminal status in `AgentRunner` and `WorkflowRunner`, recompute tokens + estimated_cost as own LLM spans sum + sum of child runs (design algorithm). Replace naive overwrite that zeroes parent rollup.  
**Where**: `src/Runtime/AgentRunner.php`, `src/Runtime/WorkflowRunner.php` (shared helper e.g. `UsageRecorder::finalizeRun` preferred)  
**Depends on**: CE-T7  
**Requirement**: CE-03  

**Done when**:
- [ ] Parent workflow run totals include nested agent/llm child runs after complete
- [ ] Standalone agent finalize still matches own spans

**Tests**: CE-T12  
**Gate**: quick  

---

### CE-T10 — `AgentNodeExecutor` pass parent run (CE-01)

**What**: Read `__studio_run_id` from state; pass parent into AgentRunner inline/stream/structured/resume.  
**Where**: `src/Runtime/NodeExecutors/AgentNodeExecutor.php`  
**Depends on**: CE-T7  
**Requirement**: CE-01  

**Done when**:
- [ ] Child run has `parent_run_id` = workflow run
- [ ] Parent totals > 0 after agent node with FakeAIProvider usage (or recorded usage)

**Tests**: extend `AgentNodeExecutorTest`  
**Gate**: quick  

---

### CE-T11 — `LlmNodeExecutor` metering (CE-01)

**What**: Pass thread + parent into `structuredInline`; for chat/stream direct provider calls, `UsageRecorder` onto parent run/trace from state (`__studio_run_id`, `__studio_trace_id`).  
**Where**: `src/Runtime/NodeExecutors/LlmNodeExecutor.php`  
**Depends on**: CE-T5, CE-T7  
**Requirement**: CE-01  

**Done when**:
- [ ] Non-structured chat writes llm span on parent workflow run
- [ ] Stream path records usage from final message when available
- [ ] Missing parent ids → no crash

**Tests**: extend `LlmNodeExecutorTest`  
**Gate**: quick  

---

### CE-T12 — Feature tests nested + pricing override (CE-01..CE-04)

**What**: End-to-end style tests: custom price → cost; nested agent under workflow → parent totals; unpriced model → 0 cost.  
**Where**: `tests/Usage/CostEstimationFeatureTest.php` (name flexible)  
**Depends on**: CE-T8, CE-T9, CE-T10, CE-T11  
**Requirement**: CE-01, CE-02, CE-03, CE-04  

**Done when**:
- [ ] Suite green; covers AC independent tests from spec

**Tests**: feature  
**Gate**: full  

---

### CE-T13 — Docs costs + config + schema (CE-02)

**What**: `guides/analytics/costs.md`; update `reference/configuration.md` and rewrite `reference/database-schema.md` off legacy `workflow_*` including new columns.  
**Where**: `docs/guides/analytics/costs.md`, `docs/reference/configuration.md`, `docs/reference/database-schema.md`  
**Depends on**: CE-T1, CE-T2 (content-accurate after CE-T12 preferred)  
**Requirement**: CE-02, success criteria docs  

**Done when**:
- [ ] Docs state estimates ≠ invoices
- [ ] Schema doc matches threads/runs/traces/spans

**Tests**: none  
**Gate**: quick  

---

## Dependency diagram ↔ tasks

| Task | Depends on (field) | Diagram |
| ---- | ------------------ | ------- |
| CE-T1 | — | ✓ |
| CE-T2 | — | ✓ |
| CE-T3 | T2 | ✓ |
| CE-T4 | T1 | ✓ |
| CE-T5 | T3, T4 | ✓ |
| CE-T6 | T5 | ✓ |
| CE-T7 | T6 | ✓ |
| CE-T8 | T7 | ✓ |
| CE-T9 | T7 | ✓ |
| CE-T10 | T7 | ✓ |
| CE-T11 | T5, T7 | ✓ |
| CE-T12 | T8, T9, T10, T11 | ✓ |
| CE-T13 | T1, T2 (+ after T12) | ✓ |

## Test co-location

| Task | Tests field | OK |
| ---- | ----------- | -- |
| CE-T4 | unit estimator | ✓ |
| CE-T5 | unit/feature recorder | ✓ |
| CE-T8/T10/T11 | extend existing feature tests | ✓ |
| CE-T12 | feature suite | ✓ |
| CE-T1/T13 | none | ✓ |

---

## Requirement traceability

| Req | Tasks |
| --- | ----- |
| CE-01 | T2, T3, T5, T6, T7, T8, T10, T11, T12 |
| CE-02 | T1, T4, T12, T13 |
| CE-03 | T2, T3, T4, T5, T6, T9, T12 |
| CE-04 | T1, T12 |
