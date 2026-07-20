# M7 External Observability — Context

**Gathered:** 2026-07-17  
**Milestone:** M7 — External observability (monitoring)  
**Status:** Execute done (OBS-01…05) on `feat/external-observability` → `v0.8.x` (AD-020). OBS-06 P3 deferred.  
**Spec:** [spec.md](./spec.md) · [design](./design.md) · [tasks](./tasks.md)

---

## Feature Boundary

M7 delivers **env-first external monitoring** (Inspector + Langfuse) on top of existing **native tracing** (M4/M5 Debugger + usage). It does not deliver a Settings UI that writes secrets, an `invoke` node, or a multi-vendor catalog.

**Post-M7 (AD-021):** LangSmith-specific integration is **dropped**. Generic OpenTelemetry export may come later as a portable OTLP exporter (P3), not as LangChain/LangSmith parity.

Product inspiration: [Langflow Observability](https://docs.langflow.org/logging) playbook — native traces + integrations via env, short docs, disable = env.

---

## Implementation Decisions (locked at Specify)

### Layers (AD-020)

| Layer | Behavior |
|--------|----------|
| Native | `TelemetryTracker` + Debugger; toggle `NEURONAI_STUDIO_NATIVE_TRACING` (default true) |
| Inspector | Opt-in via `INSPECTOR_INGESTION_KEY` + `observability.inspector.enabled` (default true = auto if key) |
| Langfuse | Opt-in via `LANGFUSE_*` + `observability.langfuse.enabled` (default true = auto if keys + package) |

Multiple layers may be active at the same time.

### EventBus caveat (required)

`observe(TelemetryTracker)` initializes the scope → Neuron **does not** auto-attach `InspectorObserver`. Inspector/Langfuse attach must be **explicit** after the tracker (or alone if native is off).

### Langfuse adapter

`axyr/laravel-langfuse` `NeuronAiObserver::onEvent` currently lacks `?string $branchId` → incompatible with Neuron 3.15 (autoload fatals). Studio ships a local **observer** that uses the Langfuse **client** only — never loads `NeuronAiObserver`. Keep `LANGFUSE_NEURON_AI_ENABLED` false. The package remains **optional** (`composer require`).

### Env-first (not UI-first)

No page that edits `.env`. Docs are the happy path. Read-only Settings status = OBS-06 P3.

### Out of this milestone

- Canvas `invoke` / allowlisted hook — deferred idea (workflow customization, not monitoring)
- LangSmith-specific integration — **dropped (AD-021)**; optional future = generic OTel only
- TraceDetail URL bridge — deferred

---

## Version / branch

- M6 Execute ✅ on `v0.7.x`; release `v0.7.0` when stable.
- M7 Develop / PRs → **`v0.8.x`** after the line is opened (AD-020). M6 patches → `v0.7.x`.

---

## Open for Design

- ~~Exact attach sites in `AgentRunner` / `WorkflowRunner` (including interpreted resume)~~ → Manager.attach; native resume attached; interpreted resume via nested AgentRunner
- ~~Minimal HTTP client vs depending only on `axyr/laravel-langfuse`~~ → optional package + adapter
- ~~Policy when native is off and no external is active~~ → zero Studio observers; explicit Manager only
- ~~`LlmNodeExecutor` generation span detail (OBS-03.3)~~ → `recordDirectLlmGeneration`

---

## Related

- [unified-runs-and-traces](../unified-runs-and-traces/spec.md) — native DB model
- [cost-estimation](../cost-estimation/spec.md) / usage — first-party metering
- Config today: `inspector_enabled` reserved in `neuronai-studio.php`
