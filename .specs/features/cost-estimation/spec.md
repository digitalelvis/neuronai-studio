# Cost Estimation Specification

## Problem Statement

O Studio já persiste `prompt_tokens` / `completion_tokens` / `total_tokens` em runs e spans, mas **não** grava provider/model no span LLM e **não** tem tabela de preços. Sem isso, o host não consegue estimar custo por execução e o Dashboard/API de export não têm base para faturamento estimado.

## Goals

- [ ] Persistir `provider` e `model` em cada span LLM criado pelo `TelemetryTracker`.
- [ ] Expor configuração de preços por modelo (prompt/completion por 1k tokens + currency).
- [ ] Calcular `estimated_cost` (ou equivalente) por span e agregar no run / nas leituras de aggregate.
- [ ] Documentar preços como estimativas editáveis pelo host.

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Fatura real / invoice do provider | Sem integração com billing APIs OpenAI/Anthropic/etc. |
| Budgets / alertas de quota | Deferred (ideia futura) |
| Dashboard BI / página Usage | Ver `usage-analytics` mínimo + M6 |
| Token cost de embeddings/RAG | Fora do M5; só LLM inference spans |

**Context:** [.specs/features/m5-analytics-billing/context.md](../m5-analytics-billing/context.md)

---

## User Stories

### P1: Model attribution on LLM spans ⭐ MVP

**User Story**: As a host developer, I want every LLM span to record which provider and model produced the tokens so that I can price usage correctly.

**Why P1**: Cost without model identity is meaningless; export API depends on this.

**Acceptance Criteria**:

1. WHEN an `inference-stop` event is handled THEN system SHALL persist `provider` and `model` on the created `StudioTraceSpan` (`type=llm`).
2. WHEN provider/model cannot be resolved from the active agent/LLM node context THEN system SHALL store `null` (or empty) for those fields and still persist token counts without failing the run.
3. WHEN a run completes THEN system SHALL keep token aggregates on `StudioRun`; model attribution remains at span level (run MAY optionally store a primary model for convenience — design discretion).

**Independent Test**: Run an agent with a known model; assert the `llm` span row has matching `provider`/`model` and token columns filled.

---

### P1: Configurable model pricing ⭐ MVP

**User Story**: As a host developer, I want to define estimated USD (or other currency) prices per 1k prompt/completion tokens per model so that cost estimates match my negotiated rates.

**Why P1**: Package ships defaults; host must override without forking code.

**Acceptance Criteria**:

1. WHEN `config/neuronai-studio.php` (published) includes a `usage.pricing` (or equivalent) map THEN system SHALL use `prompt_per_1k` and `completion_per_1k` keyed by `provider` + `model`.
2. WHEN a model is missing from the pricing map THEN system SHALL treat estimated cost as `0` (or null) without erroring aggregation.
3. WHEN the host overrides pricing via config/env publish THEN estimates SHALL reflect the override without code changes.
4. WHEN documentation is published THEN it SHALL state that values are estimates, not provider invoices.

**Independent Test**: Set a custom price for `openai`/`gpt-4o-mini`, run inference, assert estimated cost matches formula `prompt/1000 * rate + completion/1000 * rate`.

---

### P1: Estimated cost calculation ⭐ MVP

**User Story**: As a developer, I want estimated cost computed from tokens × pricing so that Dashboard and export API can show spend without each caller reimplementing the formula.

**Why P1**: Single source of truth for cost math.

**Acceptance Criteria**:

1. WHEN pricing exists for a span's provider/model THEN system SHALL compute estimated cost for that span using prompt and completion token counts.
2. WHEN aggregating a run THEN system SHALL expose total estimated cost as the sum of priced LLM spans (unpriced spans contribute 0).
3. WHEN currency is configured THEN aggregates SHALL report a single currency code (default `USD`); mixed-currency models are out of scope (one currency per install).

**Independent Test**: Two spans with different models and prices → run total equals sum of per-span estimates.

---

### P2: Seed defaults for catalog models

**User Story**: As a Studio installer, I want sensible default prices for models listed in the provider catalog so that estimates work out of the box.

**Why P2**: Improves demo UX; overrides remain required for production accuracy.

**Acceptance Criteria**:

1. WHEN the package ships THEN pricing defaults SHALL cover models currently listed under `providers.*.models` in config (best-effort approximate rates).
2. WHEN a new model is added to the catalog later THEN absence of a default price SHALL NOT break runs (cost 0).

**Independent Test**: Fresh install → export/Dashboard shows non-zero estimate for a catalog model that has a default price entry.

---

## Edge Cases

- WHEN provider returns usage `null` THEN tokens stay 0 and estimated cost is 0.
- WHEN model string on the agent differs from pricing key (alias / version suffix) THEN system SHALL exact-match config keys only (no fuzzy match in v1); document how to add aliases in config.
- WHEN only prompt tokens are billed specially (cached tokens etc.) THEN v1 ignores special units — prompt + completion only.
- WHEN pricing rates are negative or malformed THEN system SHALL treat as 0 or reject invalid config at read time (design: prefer coerce to 0 with log).

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| CE-01 | P1: Model attribution | Tasks | In Tasks |
| CE-02 | P1: Configurable pricing | Tasks | In Tasks |
| CE-03 | P1: Cost calculation | Tasks | In Tasks |
| CE-04 | P2: Seed defaults | Tasks | In Tasks |

**Coverage:** 4 total, mapped in [tasks.md](./tasks.md)

---

## Success Criteria

- [ ] LLM spans store provider + model whenever resolvable.
- [ ] Host can override pricing in published config.
- [ ] Estimated cost available for a run (sum of spans) and reusable by export API + Dashboard.
- [ ] Docs: `guides/analytics/costs.md` + `reference/configuration.md` updated.

---

## Dependencies

- **Depends on:** M4 `unified-runs-and-traces` (tokens already persisted) — done.
- **Blocks:** `usage-export-api` (needs cost fields), `usage-analytics` (minimal cost cards).

## Documentation mapping

| Doc | Change |
| --- | ------ |
| `docs/guides/analytics/costs.md` | New — formula, config, caveats |
| `docs/reference/configuration.md` | `usage.pricing` section |
| `docs/reference/database-schema.md` | provider/model/(cost) columns on spans |
