# External Observability — Tasks

**Design**: [design.md](./design.md) · **Spec**: [spec.md](./spec.md)  
**Status**: Done — Execute complete (OBS-T1…T7)  
**Linha**: `v0.8.x` · **Milestone**: M7

---

## Execution Plan

```
OBS-T1 (config) → OBS-T2 (Manager + Langfuse adapter)
                      ↓
              OBS-T3 (wire runners) ──→ OBS-T4 (LlmNodeExecutor)
                      ↓
              OBS-T5 (tests)
                      ↓
              OBS-T6 (docs + artisan) → OBS-T7 (STATE/ROADMAP/spec sync)
```

---

### OBS-T1 — Config `observability.*` (OBS-01, OBS-04)

**What**: Add `neuronai-studio.observability` (native_tracing, inspector, langfuse, metadata/tags); keep `inspector_enabled` as alias env.  
**Where**: `config/neuronai-studio.php`  
**Depends on**: None  
**Done when**:
- [ ] `NEURONAI_STUDIO_NATIVE_TRACING` defaults true
- [ ] Inspector/Langfuse enabled default true (env-first)
- [ ] `LANGFUSE_PUBLIC_KEY` / `SECRET_KEY` / `BASE_URL` (+ `HOST` alias) wired
**Tests**: none (config)  
**Gate**: build  
**Commit**: `feat(observability): add observability config section`

---

### OBS-T2 — ObservabilityManager + Langfuse adapter (OBS-02, OBS-03, OBS-04)

**What**: Implement Manager (attach order, toggles, best-effort errors, warn-once) and `LangfuseNeuronObserverAdapter`.  
**Where**: `src/Observability/ObservabilityManager.php`, `LangfuseNeuronObserverAdapter.php`  
**Depends on**: OBS-T1  
**Done when**:
- [ ] attach registers native / Inspector / Langfuse per toggles
- [ ] Inspector uses `Inspector\Neuron\InspectorObserver::instance()` when key present
- [ ] Langfuse no-ops without package/keys
- [ ] `recordDirectLlmGeneration` best-effort
**Tests**: co-located in OBS-T5  
**Gate**: quick  
**Commit**: `feat(observability): add ObservabilityManager and Langfuse adapter`

---

### OBS-T3 — Wire AgentRunner + WorkflowRunner (OBS-01, OBS-02)

**What**: Replace TelemetryTracker-only observe with Manager.attach at all AgentRunner and WorkflowRunner sites (incl. resumeNative).  
**Where**: `src/Runtime/AgentRunner.php`, `src/Runtime/WorkflowRunner.php`  
**Depends on**: OBS-T2  
**Done when**:
- [ ] Native off → no TelemetryTracker observe
- [ ] Inspector still attached when active even if native on
- [ ] Resume native path attaches
**Tests**: OBS-T5  
**Gate**: quick  
**Commit**: `feat(observability): attach external observers via Manager in runners`

---

### OBS-T4 — LlmNodeExecutor direct generation (OBS-03.3)

**What**: After direct chat/stream, call `recordDirectLlmGeneration`.  
**Where**: `src/Runtime/NodeExecutors/LlmNodeExecutor.php`  
**Depends on**: OBS-T2  
**Done when**:
- [ ] Chat and stream paths call Manager; missing package does not break run
**Tests**: OBS-T5  
**Gate**: quick  
**Commit**: `feat(observability): record Langfuse generation for direct LLM nodes`

---

### OBS-T5 — Tests (OBS-01…04)

**What**: Unit tests for Manager toggles, Inspector attach, Langfuse no-op/adapter, native-off, runner wiring smoke.  
**Where**: `tests/Observability/`  
**Depends on**: OBS-T3, OBS-T4  
**Done when**:
- [ ] Fake observers prove N attaches when N toggles on
- [ ] Native off → no StudioTraceSpan from tracker path (or tracker not constructed)
- [ ] Missing Langfuse package → no exception
- [ ] Gate green
**Tests**: unit  
**Gate**: `./vendor/bin/phpunit tests/Observability` (+ related runner subset)  
**Commit**: `test(observability): cover ObservabilityManager and toggles`

---

### OBS-T6 — Docs + install command (OBS-05)

**What**: Guides native/inspector/langfuse; update configuration, artisan, installation, runtime-and-traces; artisan `install-observability`.  
**Where**: `docs/guides/observability/*`, docs refs, `InstallObservabilityCommand`, ServiceProvider  
**Depends on**: OBS-T3  
**Done when**:
- [ ] Docs match ROADMAP M7 index
- [ ] Command prints checklist for inspector|langfuse
**Tests**: none (docs/command print)  
**Gate**: build  
**Commit**: `docs(observability): add env-first Inspector and Langfuse guides`

---

### OBS-T7 — Spec/STATE/ROADMAP sync

**What**: Mark OBS-01…05 done; update M7 snapshot; context status Execute done.  
**Depends on**: OBS-T5, OBS-T6  
**Done when**:
- [ ] ROADMAP M7 criterion status reflects code complete
- [ ] STATE Current Work updated
**Commit**: `chore(specs): mark M7 external-observability execute complete`

---

## Traceability

| Req ID | Tasks |
| ----- | ----- |
| OBS-01 | T1, T3, T5 |
| OBS-02 | T2, T3, T5 |
| OBS-03 | T2, T4, T5 |
| OBS-04 | T1, T2, T5 |
| OBS-05 | T6 |
| OBS-06 | deferred (P3) |

---

## Out of Execute

- OBS-06 Settings status page (P3)
- LangSmith, invoke node, TraceDetail URL bridge
