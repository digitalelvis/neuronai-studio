# External Observability Specification

## Problem Statement

The Studio already ships **native tracing** (Debugger: `TelemetryTracker` → `StudioTrace` / spans, usage/cost). What is missing is the **external monitoring** layer that the market expects from LLM builders (see [Langflow traces](https://docs.langflow.org/traces): local traces + env-driven exporters).

Additionally, `inspector_enabled` is reserved but **not wired**: calling `$agent->observe(TelemetryTracker)` initializes the Neuron EventBus scope, so `InspectorObserver` is not auto-attached and `INSPECTOR_INGESTION_KEY` alone does not send Studio run events to Inspector.

## Goals

- [x] Export Neuron events (inference, tools, workflow) to **Inspector** (native) and **Langfuse** (opt-in) without replacing the Debugger
- [x] **Env-first** activation (credentials present = on; `enabled=false` forces off) — same mental model as [Langfuse on Langflow](https://docs.langflow.org/integrations-langfuse)
- [x] **Native tracing** toggle (`NEURONAI_STUDIO_NATIVE_TRACING`, default true) mirroring `LANGFLOW_NATIVE_TRACING`
- [x] Short per-integration docs (native / inspector / langfuse); no secrets UI

## Out of Scope

| Item | Reason |
|------|--------|
| LangSmith-specific driver | Dropped (AD-021) — LangChain-centric; no PHP SDK. Generic OTel may come later as P3 |
| Settings UI that edits `.env` / secrets | Anti-pattern; docs + env are enough (read-only status = P3) |
| Canvas `invoke` / hook node | General extension; not required for monitoring — deferred |
| Arize / Opik / Traceloop / Grafana catalog | Overkill; host Laravel owns generic logs/APM |
| Replacing TelemetryTracker / Debugger | Native and external are complementary layers |
| Prompt management / evals UI in Studio | Belongs in Langfuse |
| TraceDetail → Inspector/Langfuse URL bridge | Polish P3 |

---

## User Stories

### P1: Native tracing toggle ⭐ MVP

**User Story**: As a developer, I want to disable Studio DB tracing when I only want external APM, so that I control overhead.

**Why P1**: Langflow parity; completes the layered model.

**Acceptance Criteria**:

1. WHEN `NEURONAI_STUDIO_NATIVE_TRACING=false` THEN the system SHALL NOT attach `TelemetryTracker` / persist Studio spans for new runs.
2. WHEN native tracing is disabled AND an external observer is active THEN agent/workflow runs SHALL still complete successfully.
3. WHEN native tracing is true (default) THEN behavior SHALL match the current Debugger + usage metering.

**Independent Test**: Run an agent with native off + Inspector on; no new `StudioTraceSpan` rows; Inspector (or mock observer) still receives events.

---

### P1: Inspector APM wiring ⭐ MVP

**User Story**: As a developer, I want Neuron Inspector APM to receive Studio agent/workflow events when I set `INSPECTOR_INGESTION_KEY`, so that I can debug production timelines outside the Studio UI.

**Why P1**: Native to Neuron; fixes the EventBus gap; no new dependency.

**Acceptance Criteria**:

1. WHEN `observability.inspector.enabled` is true AND `INSPECTOR_INGESTION_KEY` is set THEN `AgentRunner` and `WorkflowRunner` SHALL call `observe(Inspector\Neuron\InspectorObserver::instance())` in addition to (or instead of, if native off) TelemetryTracker.
2. WHEN inspector enabled is false OR the key is missing THEN the system SHALL NOT attach InspectorObserver (no-op).
3. WHEN TelemetryTracker already observed the workflow scope THEN Inspector SHALL still be attached explicitly (must not rely on EventBus auto-default).
4. Alias `inspector_enabled` in config SHALL map to `observability.inspector.enabled` for one release (deprecation note in docs).

**Independent Test**: With key set, run a playground agent; transaction appears in Inspector.dev (or unit test asserts `observe` called with InspectorObserver).

---

### P1: Langfuse env-first export ⭐ MVP

**User Story**: As a developer, I want to send LLM traces to Langfuse by setting public/secret keys (and base URL), so that I get cost/prompt/eval tooling without building it in Studio.

**Why P1**: Market standard for workflow builders; self-hostable.

**Acceptance Criteria**:

1. WHEN `observability.langfuse.enabled` is true AND `LANGFUSE_PUBLIC_KEY` + `LANGFUSE_SECRET_KEY` are set AND the Langfuse client/package is available THEN runners SHALL attach a Langfuse observer compatible with Neuron `ObserverInterface` (including `?string $branchId`).
2. WHEN keys are missing OR enabled is false OR the package is absent THEN the system SHALL no-op (warning at most once / on attach, never break the run).
3. WHEN an LLM node calls the provider outside the Agent loop (`LlmNodeExecutor`) AND Langfuse is active THEN the system SHALL record a generation/span for that call (prefer implement in MVP).
4. Env naming SHALL follow market practice: `LANGFUSE_PUBLIC_KEY`, `LANGFUSE_SECRET_KEY`, `LANGFUSE_BASE_URL` (accept `LANGFUSE_HOST` as alias if using a third-party package).

**Independent Test**: Fake/mock Langfuse observer receives `inference-stop` (or equivalent) from `AgentRunner::runInline`; missing package → run succeeds.

---

### P1: ObservabilityManager + config ⭐ MVP

**User Story**: As a package maintainer, I want a single attach point for external observers so that runners stay thin and toggles stay consistent.

**Why P1**: Avoids scattering `if (inspector)` across AgentRunner/WorkflowRunner paths.

**Acceptance Criteria**:

1. WHEN a run starts THEN `ObservabilityManager::attach($target, $meta)` SHALL register all active external observers after the native tracker decision.
2. WHEN config is published THEN `neuronai-studio.observability` SHALL document `native_tracing`, `inspector`, `langfuse`, optional `metadata`/`tags`.
3. Multiple integrations MAY be active at once (Inspector + Langfuse + native).

**Independent Test**: Unit test with fake drivers; attach calls N observers when N toggles are active.

---

### P2: Install command + docs

**User Story**: As a developer, I want a short install checklist (and optional artisan helper for Langfuse) so that setup matches Langflow “set env → run → see dashboard”.

**Why P2**: DX; does not block wiring if docs exist.

**Acceptance Criteria**:

1. Docs exist under `docs/guides/observability/` (or equivalent): native, inspector, langfuse — prerequisites → env → run → where to look.
2. Optional: `php artisan neuronai-studio:install-observability {inspector|langfuse}` prints checklist / composer require for Langfuse.
3. `docs/reference/configuration.md` updated; `inspector_enabled` no longer “reserved only”.

**Independent Test**: Follow inspector doc with key; see events. Follow langfuse doc with keys; see dashboard or mock.

---

### P3: Settings status page (read-only)

**User Story**: As a Studio operator, I want a status page showing which integrations are active (key present / package installed), without editing secrets in the browser.

**Why P3**: Nice-to-have; Langflow resolves this with docs alone.

**Acceptance Criteria**:

1. WHEN visiting the observability settings route THEN the system SHALL show native/inspector/langfuse status and env hint snippets (no write to `.env`).

---

## Edge Cases

- WHEN Langfuse package’s `NeuronAiObserver` lacks `$branchId` THEN Studio SHALL use an adapter implementing the full `ObserverInterface` (do not call the incompatible class directly).
- WHEN Inspector key is set but `inspector.enabled=false` THEN no Inspector attach.
- WHEN native is off and no external integration is active THEN runs SHALL work with zero Studio observers (document EventBus default Inspector policy in design — prefer explicit attach via Manager).
- WHEN attach/observability flush throws THEN the system SHALL catch/log and not fail the user-facing run (best-effort export).
- WHEN interpreted resume paths omit `observe` today THEN design/tasks SHALL close the gap for external observers (parity with first-run).

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| OBS-01 | P1: Native tracing toggle | Execute | Done |
| OBS-02 | P1: Inspector wiring | Execute | Done |
| OBS-03 | P1: Langfuse env-first | Execute | Done |
| OBS-04 | P1: ObservabilityManager + config | Execute | Done |
| OBS-05 | P2: Docs + install command | Execute | Done |
| OBS-06 | P3: Settings status page | Specify | Deferred |

**Coverage:** 6 total, 5 mapped to tasks (OBS-T1…T7); OBS-06 deferred P3

**ID prefix:** `OBS`

---

## Success Criteria

- [x] With only `INSPECTOR_INGESTION_KEY`, Studio runs appear in Inspector (EventBus gap fixed)
- [x] With Langfuse keys + package, inference/tool events export without breaking runs
- [x] Native Debugger remains default-on; can be disabled via env
- [x] Docs allow a developer to enable Inspector or Langfuse in under 5 minutes
- [x] No Settings UI required for MVP

---

## References

- Plan: observability (Cursor plan, Langflow-inspired)
- Langflow: [Traces](https://docs.langflow.org/traces), [Langfuse](https://docs.langflow.org/integrations-langfuse), [LangSmith](https://docs.langflow.org/integrations-langsmith), [Logs](https://docs.langflow.org/logging)
- In-repo: `TelemetryTracker`, `config/neuronai-studio.php` (`inspector_enabled`), Neuron `EventBus` / `Inspector\Neuron\InspectorObserver`
