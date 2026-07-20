# Agent Memory Controls — Tasks

**Spec**: [spec.md](./spec.md) · **Context**: [../m8-performance-memory-context/context.md](../m8-performance-memory-context/context.md)  
**Status**: Execute in progress on `v0.9.x`  
**Linha**: `v0.9.x` · **Ordem M8**: 1/3  
**Design**: skipped — inline design decisions noted per task.

---

## Execution Plan

```
AMC-T1 → AMC-T2 → AMC-T3 [P]
       └→ AMC-T4 [P]
AMC-T2, AMC-T4 → AMC-T5 → AMC-T6 → AMC-T7
AMC-T2 → AMC-T8 [P] → AMC-T9
AMC-T6, AMC-T9 → AMC-T10
```

No migration needed: `memory_config` JSON column already exists (cast, fillable, copied by `TemplateInstaller`).

---

### AMC-T1 — `memory_config` envelope schema + validation (AMC-01)

**What**: Define the envelope keys (context window, driver, summarization on/off + thresholds, budget keys reserved for CTX) and server-side validation rules.  
**Where**: envelope value object / validation (new `src/Runtime/Memory/MemoryConfig.php` or similar), `AgentDefinition`  
**Depends on**: None  
**Reuses**: `AgentDefinition.memory_config` column (dead today)  
**Inline design**: exact key names = agent's discretion (context.md); unknown keys ignored for forward compatibility.  
**Done when**:

- [x] Envelope parses/validates from array; invalid values (window ≤ 0, unknown driver, bad thresholds) rejected
- [x] Null/empty envelope = explicit "inherit everything" state

**Tests**: unit (envelope parsing + validation)  
**Gate**: quick ✅ (12 tests)  
**Status**: ✅ Complete

---

### AMC-T2 — Runtime resolution in AgentRunner + node override (AMC-01)

**What**: Read the envelope when building `DynamicAgent`; resolve per-node overrides from agent-node `data` (override wins, empty inherits).  
**Where**: `AgentRunner`, `AgentNodeExecutor`, `DynamicAgent` constructor wiring  
**Depends on**: AMC-T1  
**Reuses**: M6 override resolution pattern (`tool_max_runs` / `parallel_tool_calls` in `agent-tool-controls`)  
**Done when**:

- [x] Configured window reaches `DynamicAgent::chatHistory()`; null envelope = today's global config path (zero regression)
- [x] Node override wins over agent envelope for that visit

**Tests**: unit/feature (resolution matrix: none / agent / agent+node)  
**Gate**: quick ✅ (7 tests)  
**Status**: ✅ Complete

---

### AMC-T3 — Driver selection (eloquent vs in_memory) (AMC-01) [P]

**What**: Honor `driver` from the envelope in `DynamicAgent::chatHistory()`; `in_memory` forces `InMemoryChatHistory` even with thread id.  
**Where**: `src/Runtime/DynamicAgent.php`  
**Depends on**: AMC-T2  
**Reuses**: existing `InMemoryChatHistory` / `EloquentChatHistory` selection logic  
**Done when**:

- [x] Driver matrix covered: absent (today's behavior), `eloquent`, `in_memory` with/without thread id

**Tests**: unit  
**Gate**: quick ✅ (5 tests)  
**Status**: ✅ Complete

---

### AMC-T4 — Summarizer service + config keys (AMC-03) [P]

**What**: Summarizer service calling a dedicated cheap provider/model from new `config/neuronai-studio.php` keys (env-backed), falling back to the agent's provider/model when unset; usage metered via the M5 pipeline.  
**Where**: new `src/Runtime/Memory/HistorySummarizer.php` (or similar), `config/neuronai-studio.php`  
**Depends on**: AMC-T1  
**Reuses**: provider construction from `AgentRunner`/`DynamicAgent`; `TelemetryTracker` usage metering  
**Inline design**: config key names = agent's discretion; summarization prompt kept internal (not user-editable in M8).  
**Done when**:

- [x] Dedicated model used when configured; agent model when not
- [x] Provider failure surfaces as a typed result (for T5 fallback), never an unhandled throw
- [x] Summarizer tokens metered on the run

**Tests**: unit (fake providers: configured / fallback / failing)  
**Gate**: quick ✅ (5 tests)  
**Status**: ✅ Complete

---

### AMC-T5 — Non-destructive trim + compaction hook (AMC-02)

**What**: Supersede `HistoryTrimmer` silent deletes on the Studio path: when history exceeds budget, either compaction (summarization on) or non-destructive trim (exclude from prompt, keep `StudioChatMessage` rows).  
**Where**: history layer around `EloquentChatHistory` / `DynamicAgent` (Studio-side wrapper; vendor untouched)  
**Depends on**: AMC-T2, AMC-T4  
**Reuses**: Neuron token estimation used by `HistoryTrimmer` (consistency requirement from spec)  
**Inline design**: interception point (wrapper vs subclass) = agent's discretion; must survive checkpoint/resume restore.  
**Done when**:

- [x] Over-budget thread: no rows physically deleted in any mode
- [x] Summarization off → prompt excludes oldest messages but rows persist
- [x] Budget smaller than a single message → latest message kept, condition recorded

**Tests**: feature (seeded over-budget threads, both modes)  
**Gate**: quick ✅ (4 tests)  
**Status**: ✅ Complete

---

### AMC-T6 — Summary message persistence + roll-forward (AMC-02)

**What**: Persist the summary as a distinguishable message replacing the trimmed prefix; roll previous summary + newly trimmed into a new single active summary; summarizer failure falls back dedicated → agent model → non-destructive trim.  
**Where**: same history layer as T5, `StudioChatMessage`  
**Depends on**: AMC-T5  
**Reuses**: `HistorySummarizer` (AMC-T4)  
**Inline design**: summary role/marker format = agent's discretion; must round-trip `EloquentChatHistory` and render distinguishably in thread UI.  
**Done when**:

- [x] One active summary max per thread; second compaction rolls forward
- [x] Fallback chain verified with failing summarizer (run completes, `summarizer_fallback` recorded)
- [x] Summary survives pause/resume (checkpoint restore includes it)

**Tests**: feature (compaction, roll-forward, fallback, resume)  
**Gate**: full suite subset ✅ (3 compaction + existing memory tests)  
**Status**: ✅ Complete  
**Note**: Summary is a normal `StudioChatMessage` row; Eloquent reload includes it (resume-safe).

---

### AMC-T7 — Compaction span metadata (AMC-02)

**What**: Record compaction events (messages summarized, token estimate before/after, fallback used) in trace span metadata.  
**Where**: history layer → `TelemetryTracker` / span metadata write  
**Depends on**: AMC-T6  
**Reuses**: `StudioTraceSpan` metadata; pattern to be shared with CTX-T6  
**Done when**:

- [x] Span metadata present when compaction ran; absent otherwise
- [x] Works with `NEURONAI_STUDIO_NATIVE_TRACING=false` (no error, metadata skipped)

**Tests**: feature  
**Gate**: quick ✅ (2 tests)  
**Status**: ✅ Complete

---

### AMC-T8 — Agent form UI (AMC-04) [P]

**What**: Memory section on the agent editor: window, driver, summarization on/off + thresholds → `memory_config` envelope; untouched form keeps envelope null.  
**Where**: agent Livewire form/blade  
**Depends on**: AMC-T2  
**Reuses**: M6 knob fields UX (`agent-tool-controls`)  
**Done when**:

- [x] Round-trip save/load; field-level validation errors on invalid values
- [x] Untouched form → `memory_config` stays null

**Tests**: feature (Livewire)  
**Gate**: quick ✅ (3 tests)  
**Status**: ✅ Complete

---

### AMC-T9 — Node inspector override + bundles (AMC-04)

**What**: Memory override fields on the agent-node inspector (canvas), stored in node `data`; rebuild canvas bundle.  
**Where**: canvas node inspector (JS), bundle build  
**Depends on**: AMC-T8  
**Reuses**: M6 node override fields (`tool_max_runs`)  
**Done when**:

- [x] Empty = inherit; set values win at runtime (covered by AMC-T2 tests)
- [x] Bundles rebuilt (IIFE, AD-001)

**Tests**: existing runtime tests + manual canvas check  
**Gate**: build ✅  
**Status**: ✅ Complete

---

### AMC-T10 — Codegen + docs (AMC-05)

**What**: Emit memory setup in codegen when expressible (comment otherwise); update docs.  
**Where**: codegen templates; `docs/guides/agents/creating-agents.md`, `docs/guides/agents/playground-and-threads.md`, `docs/guides/workflows/node-types/ai-nodes.md`, `docs/reference/configuration.md`, `docs/reference/database-schema.md`  
**Depends on**: AMC-T6, AMC-T9  
**Done when**:

- [ ] Codegen snapshot includes memory setup when configured
- [ ] Docs rows from the ROADMAP M8 index updated; "reserved for future memory features" wording removed

**Tests**: codegen snapshot  
**Gate**: docs
