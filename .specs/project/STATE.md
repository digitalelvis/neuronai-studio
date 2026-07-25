# State

**Last Updated:** 2026-07-23
**Development line (features):** `v0.10.x` (M9)
**Patch line:** `v0.9.x`
**Latest published:** `v0.10.0` on Packagist / `main`
**Current Work:** M9 on `feat/knowledge-base-rag` → `v0.10.x` (single PR): RAG hardening, Langflow-level Studio UX, codegen local gates, canvas tool bindings, and breaking migrate to `neuron-core/neuron-ai`. TraceDetail bridge + OBS-06/OTel stay deferred.

---

## Recent Decisions (Last 60 days)

### AD-023: M9 = Studio UX + RAG harden + neuron-ai direct on `v0.10.x` (2026-07-23)

**Decision:** After publishing `v0.10.0` (`canvas-invoke-node`), open **M9** on line `v0.10.x` as one PR (`feat/knowledge-base-rag`): (1) expand Neuron vector stores + async ingest/reindex + KB docs; (2) Langflow-level canvas + playground shell (sessions/traces); (3) gate codegen export/preview local-only; (4) bind tools to agents via canvas edges; (5) drop `neuron-core/neuron-laravel` and require `neuron-core/neuron-ai` with Studio publishing `config/neuron.php`. No formal per-slice feature specs for this wave — design follows shipped commits + docs under `guides/knowledge-bases/` and canvas/playground guides.
**Reason:** Product gap after M8 is authoring UX parity and production-safe RAG/codegen, not another runtime milestone. Direct `neuron-ai` removes the Laravel bridge from the install path.
**Trade-off:** Single large PR (no split). Breaking for hosts still on `neuron-laravel`. Hybrid/MMR retrieval remains P3 polish.
**Impact:** ROADMAP M9 in progress; STATE/RELEASE point at `v0.10.x`; patch line = `v0.9.x`.

### AD-022: M8 feature split + compaction memory + `v0.9.x` Execute line (2026-07-20)

**Decision:** M8 decomposes into three features: **`agent-memory-controls`** (P1, AMC-xx) and **`context-engineering`** (P1, CTX-xx) as the MVP ("1C + prompt assembly" — memory controls + FULL context engineering including summarization; prompt assembly covers history + RAG chunks + tool results + large state fields), plus **`parallel-tool-approval`** (P2, PTA-xx — specified now, executed only after the two P1s). Memory persistence = **compaction**: when history exceeds budget, the trimmed prefix is replaced by a persisted summary message in the Eloquent thread — superseding Neuron `HistoryTrimmer` silent deletes. Summarizer = **dedicated configurable cheap model** (own provider/model config keys) with fallback to the agent's own provider/model, then to non-destructive trim on failure. Granularity = the existing dead `AgentDefinition.memory_config` JSON column activated as a **single envelope** (window, driver, summarization, budgets) + per-node override in agent-node `data` (M6 `tool_max_runs` pattern). M8 Execute targets a new **`v0.9.x`** feature line (branch opens at Execute); `v0.8.x` becomes the patch line.
**Reason:** `memory_config` is dead config and the trim path silently destroys durable history — the biggest quality gap for long-running agents. Budgeting only history would leave RAG/tool/state injection unbounded (the actual prompt-size drivers). PTA closes the known fail-closed fork gap without blocking the memory MVP. A fresh minor line keeps `v0.8.x` clean for M7 patches.
**Trade-off:** Design phase intentionally skipped (design inline in tasks) — spec quality must carry the load. Mechanical truncation only for RAG/tool/state (no semantic compression). One-approval-per-resume for parallel branches (no batch UX).
**Impact:** Specs/tasks in [agent-memory-controls](../features/agent-memory-controls/spec.md), [context-engineering](../features/context-engineering/spec.md), [parallel-tool-approval](../features/parallel-tool-approval/spec.md); milestone context/index in [m8-performance-memory-context](../features/m8-performance-memory-context/context.md). ROADMAP M8 `specified` + `v0.9.x` header. Resolves open decisions "M8 feature split" and "tool approval in parallel branches."

### AD-021: M8 = performance / memory / context — drop LangSmith; OTel P3 only (2026-07-20)

**Decision:** After M7 (`v0.8.0`), the product priority is **agent and workflow performance**: best use of model/resources, durable memory, and context engineering — not more monitoring vendors. **Remove LangSmith** as a planned integration (LangChain-centric; poor PHP/Neuron fit). Keep a single deferred item for **generic OpenTelemetry export** (any OTLP backend, including LangSmith-as-backend if a host wants it) at **P3 / when-needed**. M8 Specify will decompose themes into features (memory controls, context budgets, runtime gaps such as tool approval in parallel branches).
**Reason:** Inspector + Langfuse + native Debugger already cover observability. Building a LangSmith-specific path is high cost / low leverage. Hosts that need OTel should get a portable exporter later, not a LangChain-parity checklist. Performance and context quality are the next differentiator for autonomous agents.
**Trade-off:** No marketing parity with Langflow’s LangSmith page. OBS-06 Settings status and canvas `invoke` stay deferred behind M8 core.
**Impact:** ROADMAP M8 planning; Deferred Ideas re-bucketed; external-observability docs/context say “OTel later,” not “LangSmith.” Context stub: [.specs/features/m8-performance-memory-context/context.md](../features/m8-performance-memory-context/context.md).

### AD-020: M7 External observability + `v0.8.x` line (2026-07-17)

**Decision:** After M6 Execute completed on `v0.7.x`, open milestone **M7 — External observability** with feature `external-observability`. Product model aligned with Langflow: **native tracing** (Debugger/TelemetryTracker already exists) + **monitoring** via Inspector and Langfuse **env-first**. Develop/PRs for M7 → `v0.8.x` after release `v0.7.0`; patch line = `v0.7.x`.
**Scope M7 MVP (OBS-01…04 + docs OBS-05):** (1) toggle `NEURONAI_STUDIO_NATIVE_TRACING`; (2) wire `Inspector\Neuron\InspectorObserver` explicitly (fixes EventBus gap when TelemetryTracker already observed); (3) Langfuse via adapter with `$branchId` + `LANGFUSE_*` keys; (4) `ObservabilityManager` as the single attach point. P3 Settings status and LangSmith out of MVP.
**Reason:** Market (Langflow) separates local traces from exporters; Studio already has native/usage (M4/M5) but `inspector_enabled` is dead config and Langfuse/LangSmith do not exist. Env-first avoids a secrets UI. LangSmith has no PHP SDK → deferred to OTel.
**Trade-off:** Does not include `invoke` node, Settings write, or multi-vendor catalog. `axyr/laravel-langfuse` needs an adapter until upstream accepts `branchId`.
**Impact:** Spec/context in [.specs/features/external-observability/](../features/external-observability/). ROADMAP M7. Deferred (later revised by AD-021): invoke node, generic OTel P3, TraceDetail URL bridge, Settings P3. LangSmith vendor path dropped.

### AD-019: M6 Runtime/Agent + `v0.7.x` line (2026-07-16)

**Decision:** After publishing `v0.6.0` (UE / M5 complete), open `v0.7.x` as the feature line for M6 — runtime/agent performance and flexibility. Patch line = `v0.6.x`. Close `v0.5.x` / `v0.6.x` for new features.
**Scope M6 (Execute order):** (1) `agent-tool-controls` — `tool_max_runs` / `parallel_tool_calls` + live tool SSE; (2) `async-run-progress` — ProgressEmitter buffer + SSE tail (no Echo); (3) `interpreted-parallel-concurrency` — Amp concurrent fork/join on the interpreted path.
**Reason:** Neuron already does multi-round tools; Studio gaps are knobs, mid-loop observability, job progress, and real concurrency (AD-007 was sequential). Advanced Billing/Usage remains deferred.
**Impact:** ROADMAP/STATE/RELEASE + M6 specs → `v0.7.x`. Open decisions on SSE-async and multi-turn-in-node resolved (see ROADMAP). Context: [.specs/features/m6-runtime-agent/context.md](../features/m6-runtime-agent/context.md).

### AD-018: `v0.5.x` (patch) + `v0.6.x` (UE / close M5) lines (2026-07-16)

**Decision:** After publishing `v0.5.0` (UA), keep `v0.5.x` as the patch line and open `v0.6.x` as the feature line for Execute of `usage-export-api`. Close `v0.4.x` for features.
**Reason:** UA shipped in its own minor; UE completes the M5 criterion (host HTTP API) and deserves a dedicated series. The `v*.*.x` ruleset already covers both lines.
**Impact:** ROADMAP/STATE/RELEASE + M5 tasks point UE → `v0.6.x`. UE PRs → `v0.6.x`; `0.5.*` patches → `v0.5.x`.

### AD-017: Active line `v0.4.x` after 0.4.0 release (2026-07-16)

**Decision:** Close `v0.3.x` as the active feature line; develop from `v0.4.x`. CE already published in `v0.4.0`. UE/UA debt and new features target PRs → `v0.4.x`.
**Reason:** Release `v0.4.0` consolidated M4 leftovers + CE on `main`/Packagist.
**Impact:** Superseded by AD-018 after `v0.5.0`.

### AD-016: UA includes Test Pretty + minimal UsageQuery (2026-07-16)

**Decision:** Expand `usage-analytics` to token/cost chips on Test Pretty (`WorkflowThread` / Completed header) in addition to Debugger + Dashboard. Dashboard uses `UsageQuery::aggregate` extracted in UA (partial UE-T2); HTTP export remains debt. Tasks UA-T1…T11.
**Reason:** Pretty is the primary harness surface; operators see latency but not spend. Blocking Dashboard on the full UE HTTP surface delays Studio value.
**Impact:** UA spec/design/tasks + ROADMAP/M5 index updated; UA shipped in `v0.5.0`.

### AD-015: UE + UA as M5 debt — do not Execute now (2026-07-15)

**Decision:** Keep `usage-export-api` and `usage-analytics` on the roadmap with specs/design/tasks intact, but **do not execute** until an explicit return. CE remains the M5 slice delivered in that wave.
**Reason:** DB metering (provider/model/cost + nest rollup) already unblocks the host via direct queries; export API and Studio surface can wait without invalidating the design.
**Impact:** UA resumed and shipped (`v0.5.0`); UE resumed under AD-018 on the `v0.6.x` line.

### AD-012: RELEASE_TOKEN for release push to main (2026-07-15)

**Decision:** Authenticate `.github/workflows/release.yml` with secret `RELEASE_TOKEN` (fine-grained PAT from an Administrator), never with `GITHUB_TOKEN`. Push the commit to `main` **before** the tag. Fail early if the secret is missing.
**Reason:** User-owned repos cannot bypass the GitHub Actions app on the ruleset; `GITHUB_TOKEN` produces orphan tags on Packagist when the `main` push is rejected (GH013).
**Impact:** One-time setup in [docs/RELEASE.md](../../docs/RELEASE.md); ruleset keeps bypass only for RepositoryRole Administrator.

### AD-014: M5 design — denormalized cost + parent rollup (2026-07-15)

**Decision:** Persist `provider`/`model`/`estimated_cost` on the LLM span and `estimated_cost` (+ optional `parent_run_id`) on the run. Pricing in `neuronai-studio.usage.pricing`. Nested agent/LLM under a workflow increments the parent run; window aggregates **exclude** children to avoid double-counting. Close metering gaps in `stream`/`streamHandler` and `LlmNodeExecutor`. Export: `GET usage` + `GET usage/runs/{run}` under the integration prefix/middleware, independent of `stream_adapters.enabled`. Dashboard: fixed 30-day window via `UsageQuery`.
**Reason:** `InferenceStop` does not carry model; workflow parent today stays at 0 tokens because LLM lives in child runs; LlmNodeExecutor chat/stream bypasses the tracker.
**Impact:** See CE/UE/UA designs. Run finalize = own spans + children aggregates.

### AD-013: M5 host-first + minimal Dashboard (2026-07-15)

**Decision:** M5 prioritizes `cost-estimation` + `usage-export-api` for host metering/billing. `usage-analytics` stays **minimal**: evolve the existing Livewire Dashboard + Debugger token badges — no dedicated Usage/BI page in this milestone. Shared context in `.specs/features/m5-analytics-billing/context.md`.
**Reason:** Token persistence already exists (M4); the product gap is a metering API for the host app. Studio only needs a light operational signal.
**Impact:** Design/implementation order: CE → UE → UA. Advanced Usage page, multi-tenant attribution, embeddings cost, and billing providers stay in Deferred Ideas.

### AD-010: Development line v0.3.x + M5 (2026-07-15)

**Decision:** Close `v0.2.x` as the active line; open `v0.3.x` from `main` aligned with Packagist `v0.3.1`. Plan M5 (Analytics & Billing) on top of tokens already persisted in `StudioTraceSpan` / `TelemetryTracker`.
**Reason:** M1–M4 already shipped in `v0.3.0`; `v0.3.1` fixed release metadata. A new minor series avoids mixing governance patches with usage/billing features.
**Impact:** Feature PRs → `v0.3.x`; release PR `v0.3.x` → `main` when M5 is stable. M5 specs: see AD-013.

### AD-011: Absorb orphan tag v0.3.1 into main (2026-07-15)

**Decision:** Merge the `chore(release): 0.3.1` commit (Packagist tag) back into `main` via hotfix, with `[skip ci]` on the merge commit, instead of a destructive retag.
**Reason:** The release-it push diverged from the tip of `main` (PR #22); Packagist pointed at a SHA outside ancestry, and tip `package.json`/`CHANGELOG` stayed at `0.3.0`.
**Impact:** `git describe` on `main` again reports `v0.3.1`; the next real release starts from that base.


### AD-009: Unified Threads, Runs, and Traces (2026-07-07)

**Decision:** Refactor Workflow and Agent execution to unify under StudioRuns and StudioThreads naming/concepts.
**Reason:** Semantic unification (runs vs traces), distributed pause support for Agents (HITL/Tool Approval), and per-TraceSpan token tracking.
**Impact:** `StudioThread`, `StudioRun`, `StudioTrace`, `StudioTraceSpan` replace legacy `WorkflowTrace`, `WorkflowTraceStep`, `WorkflowCheckpoint`.

### AD-008: M4 stream-adapters — internal/external split + interpreted→adapter bridge (2026-07-03)

**Decision:** Kickoff M4 (`stream-adapters`). External endpoints (Vercel AI SDK, AG-UI) live in a **separate** route group/file (`routes/integration.php`, prefix `api/neuronai`, own configurable middleware) registered conditionally by `stream_adapters.enabled`. Zero change to the internal playground/harness (controllers, `fetchSse.js`, SessionAdapters, `StudioChat`). For workflow, because Studio runtime is **interpreted** (own SSE, not Neuron chunks), the bridge converts events (`token`/`tool_call`/`tool_result`) into chunks (`TextChunk`/`ToolCallChunk`/`ToolResultChunk`) and feeds `$adapter->transform()` (recommended Option A; final AD in Phase 1 / SA-T6).
**Reason:** `WorkflowHandler::events($adapter)` only exists on native runtime; Studio runs interpreted. Reusing official adapters (guaranteed format) without touching the internal path keeps zero regression and protocol parity.
**Trade-off:** Bridge adds an event-conversion layer; interrupt (Human node) needs explicit mapping to the protocol terminal event + `trace_id` for `resume/{protocol}`.
**Impact:** `StreamAdapterRegistry`, `stream_adapters` config, `routes/integration.php`, `AgentRunner::streamHandler`, `AgentIntegrateStreamController`, `WorkflowStreamBridge`, `WorkflowIntegrateStreamController`, `WorkflowIntegrateResumeController`, `/stream-adapters` catalog, Connect Panel. See [tasks](../features/stream-adapters/tasks.md).

### AD-007: Interpreted runtime for parallel execution (2026-07-03)

**Decision:** Fork/Join use **interpreted** runtime — `ForkNodeExecutor` runs each branch sequentially in an isolated `BuilderWorkflowState` (clone) until join, and `JoinNodeExecutor` merges results by branch id. Native codegen emits a valid `ParallelEvent` subclass for export, but concurrent orchestration via Neuron’s `AsyncExecutor` is not exercised at Studio runtime.
**Reason:** Per-branch state isolation + partial resume (reuse checkpoint/HITL) are simpler and more deterministic under the interpreted loop; avoids Amp/AsyncExecutor dependency on the harness path.
**Trade-off:** No real I/O parallelism on interpreted runtime (independent but sequential branches); tool approval inside a branch is not split per branch (Human interrupt only).
**Impact:** `ParallelBranchRunner`, `ForkNodeExecutor`/`JoinNodeExecutor`, `ParallelBranchInterruptException`, checkpoint `kind: parallel` in `WorkflowRunner`, `GraphValidator::validateParallel`, SSE `branch_started`/`branch_completed`/`parallel_interrupt`.

### AD-006: Checkpoints as opt-in decorator + EloquentPersistence (2026-07-03)

**Decision:** Generalize checkpoints with a `CheckpointService` + `neuronai_studio_workflow_checkpoints` table. Expensive nodes (agent/llm/rag/tool) opt in via `data.checkpoint: true` and are wrapped by a `CheckpointingExecutor` decorator. Native workflows use `EloquentPersistence` (implements `SerializablePersistenceInterface`) to persist `WorkflowInterrupt`.
**Reason:** Avoids re-running expensive provider calls on resume without coupling cache logic into every executor; keeps Human/ToolApproval per-trace checkpoint intact.
**Trade-off:** Key `sha256(trace_id|node_id|iteration|input_hash)` stores only the node state diff (merged on hit); volatile internal keys are ignored in the hash so they do not invalidate incorrectly.
**Impact:** `CheckpointService`, `CheckpointingExecutor`, `WorkflowCheckpoint` model, nullable FK + `workflow_key` migration, `checkpoints.enabled/ttl` config, `checkpoints:purge` command, `EloquentPersistence`.

### AD-005: Tool approval via NeuronAI `ToolApproval` middleware (2026-07-03)

**Decision:** Reuse `NeuronAI\Agent\Middleware\ToolApproval` middleware on `DynamicAgent`; convert the agent’s `WorkflowInterrupt`/`ApprovalRequest` into `ToolApprovalRequiredException` in the `AgentRunner` layer, following the Human node pause pattern.
**Reason:** Avoids reimplementing tool-call detection; keeps pause/checkpoint consistent with `pauseForHumanInput` and `awaiting_input` status.
**Trade-off:** Slices 1–2 approve **all** tools (empty config). Slice 2 persists the serialized `WorkflowInterrupt` in the checkpoint and restores it for real resume; UI/codegen land in slice 3.
**Impact:** `require_tool_approval` on `AgentDefinition` + agent-node override; new `awaiting_tool_approval` trace status (string column, no migration); SSE `tool_approval_required` + `tool_approval_resolved`; resume `approve|reject` via `POST .../resume/stream` (sync) and `.../resume` (async job); optional `rejected` handle on agent node. Note: tools with `Closure` callbacks break interrupt serialization — Studio uses class-based tools.

### AD-004: Development line v0.2.x (2026-06-30)

**Decision:** Open `v0.2.x` from `main` (`v0.1.2`) for milestone M1 (north star: autonomous multimodal agents + cyclic graphs).
**Reason:** `v0.1.x` delivered Studio foundation (harness, code bridge, partial multimodal); cycles and real RAG need a minor bump.
**Trade-off:** `v0.0.x` remains the historical line; new PRs go to `v0.2.x`.
**Impact:** See [ROADMAP.md](ROADMAP.md); first deliverable = `loop` node + cycle validation.

### AD-003: Roadmap north star — cyclic + autonomous multimodal (2026-06-30)

**Decision:** Prioritize M1 with three P0 features (`workflow-cyclic-graphs`, `autonomous-multimodal-agents`, `workflow-rag`) before P1/P2.
**Reason:** Current state is DAG-only, stub `RagNodeExecutor`, `GraphExecutionLoop` without guardrail — blocks autonomous agents with media in loops.
**Trade-off:** Nine planned features increase surface area; M1 is the minimum viable for the north star.
**Impact:** See [.specs/project/ROADMAP.md](ROADMAP.md).

### AD-001: IIFE output for studio JS bundles (2026-06-24)

**Decision:** Build `workflow-canvas.bundle.js` and `studio-chat.bundle.js` as IIFE (`NeuronAIStudioCanvas`, `NeuronAIStudioChat`).
**Reason:** Both bundles ship React with overlapping minified top-level `const` names; loading both on the workflow editor caused `Identifier 'fo' has already been declared`.
**Trade-off:** Slightly larger bundles; CSS now injected via JS instead of separate `.css` files from Vite.
**Impact:** Workflow editor loads canvas + chat without global scope collision; `window.mountStudioChat` available for Test tab.

### AD-002: POST SSE for workflow runs and human resume (2026-06-24)

**Decision:** Workflow test harness uses POST stream endpoints with checkpoint/resume for Human nodes.
**Reason:** Supports attachments, context payload, and conversational resume without modals.
**Trade-off:** Breaking change from GET workflow run stream.
**Impact:** `HumanNodeExecutor` throws `HumanInputRequiredException`; `WorkflowRunner` persists checkpoint with `awaiting_input` status.

---

## Active Blockers

- No active blockers.

---

## M1 progress snapshot

| Feature | Status | Notes |
|---------|--------|-------|
| `workflow-cyclic-graphs` | ✅ done | P0 + P1 delivered |
| `autonomous-multimodal-agents` | ✅ done | AMA-09 docs delivered |
| `workflow-rag` | ✅ done | Slices 1–3 (backend, UI, codegen, docs) |

---

## M2 progress snapshot

| Feature | Status | Notes |
|---------|--------|-------|
| `workflow-structured-output` | ✅ done | T1–T17 ✅; T12 partial — dot-notation hint only on condition (loop has no inspector) |
| `workflow-tool-approval` | ✅ done | Slices 1–3 ✅ (backend, resume/API, UI+codegen+docs) |
| `workflow-token-streaming` | ✅ done | Slice 1 (backend token SSE) ✅; slice 2 (canvas toggle + docs polish) ✅ |

---

## M3 progress snapshot

| Feature | Status | Notes |
|---------|--------|-------|
| `workflow-queue-runner` | ✅ done | T1–T11 ✅ — `RunWorkflowJob`, `ResumeWorkflowJob`, async run/resume API, polling, docs |
| `workflow-checkpoints-persistence` | ✅ done | CP-01..08 ✅ — service + decorator + EloquentPersistence + purge |
| `workflow-parallel-execution` | ✅ done | PE-01..09 ✅ — fork/join runtime, branch resume, codegen, canvas (PE-08 preview partial) |

---

## M4 progress snapshot

| Feature | Status | Notes |
|---------|--------|-------|
| `stream-adapters` | ✅ done | branch `feat/stream-adapters`; Phases 1–3 delivered (SA-T1..T13); suite 279 green |
| `unified-runs-and-traces` | ✅ done | T1–T7 complete; migrations, models, adapters, token tracking, 279 tests green |

---

## M5 progress snapshot

| Feature | Status | Notes |
|---------|--------|-------|
| `cost-estimation` | ✅ done | CE-T1…T13 — shipped `v0.4.0` |
| `usage-analytics` | ✅ done | UA-T1…T11 — shipped `v0.5.0` |
| `usage-export-api` | ✅ done | UE-T1…T7 — shipped `v0.6.0` |

---

## M6 progress snapshot

| Feature | Status | Notes |
|---------|--------|-------|
| `agent-tool-controls` | ✅ done | knobs + live tool SSE on `v0.7.x` |
| `async-run-progress` | ✅ done | ProgressEmitter + SSE tail |
| `interpreted-parallel-concurrency` | ✅ done | Amp concurrent fork/join + sequential fallback |

**M6 code ✅ — published in `v0.7.0`.**

---

## M7 progress snapshot

| Feature | Status | Notes |
|---------|--------|-------|
| `external-observability` | ✅ done | OBS-01…05; OBS-06 P3 deferred; branch `feat/external-observability` |

**M7 code ✅ — published in `v0.8.0`.**

---

## M8 progress snapshot

| Feature | Status | Notes |
|---------|--------|-------|
| `agent-memory-controls` (P1) | ✅ done | AMC-T1…T10 on `v0.9.x` — [spec](../features/agent-memory-controls/spec.md) · [tasks](../features/agent-memory-controls/tasks.md) |
| `context-engineering` (P1) | ✅ done | CTX-T1…T9 on `v0.9.x` — [spec](../features/context-engineering/spec.md) · [tasks](../features/context-engineering/tasks.md) |
| `parallel-tool-approval` (P2) | ✅ done | PTA-T1…T7 on `v0.9.x` — [spec](../features/parallel-tool-approval/spec.md) · [tasks](../features/parallel-tool-approval/tasks.md) |
| LangSmith-specific | dropped | AD-021 |
| Generic OTel | P3 deferred | when-needed only |

**Execute order (AD-022):** AMC → CTX → PTA on `v0.9.x` (branch open from `main` @ `v0.8.1`; design inline in tasks; 26 tasks — [index](../features/m8-performance-memory-context/tasks.md)).

---

## Lessons Learned

### L-001: Multiple Vite bundles need isolated scope (2026-06-24)

**Context:** Workflow editor loads two production bundles on the same page.
**Problem:** Default Vite output leaked shared minified identifiers into global lexical scope → SyntaxError on page load.
**Solution:** `format: 'iife'` per bundle in `vite.config.js`.
**Prevents:** Duplicate identifier errors when adding more studio bundles to the same layout.

### L-002: Private disk attachments need authenticated preview route (2026-06-30)

**Context:** Multimodal workflow/agent test harness.
**Problem:** `Storage::url()` pointed at `/storage/...` (403) on private `local` disk.
**Solution:** `GET /studio/attachments/file?storage_key=` + keep blob preview in the composer.

### L-003: Fork resume must reprocess not-yet-started branches (2026-07-03)

**Context:** Interrupt (Human node) inside a parallel branch.
**Problem:** Resuming only the pending branch dropped branches that had not started yet (those after the interrupt in sequential order).
**Solution:** On resume, `ForkNodeExecutor` iterates all branches: skips completed ones (from checkpoint), resumes the pending one with injected input, and runs not-yet-started ones from scratch.
**Prevents:** Silent loss of branch results in workflows with >1 branch and HITL.

### L-004: EventBus does not auto-attach Inspector if observe() already ran (2026-07-17)

**Context:** M7 planning / Inspector APM.
**Problem:** `TelemetryTracker` calls `$agent->observe(...)` and initializes the EventBus scope; Neuron then **does not** register the default `InspectorObserver` even with `INSPECTOR_INGESTION_KEY`.
**Solution:** Explicitly attach `Inspector\Neuron\InspectorObserver::instance()` via `ObservabilityManager` when enabled + key present.
**Prevents:** “Key set but nothing on Inspector.dev” for Studio runs.

### L-005: Workflow slug must not be recalculated on every save (2026-07-03)

**Context:** Canvas auto-save before running a test (`saveGraphBeforeRun` → `Editor::save()`), with two workflows sharing the same name (e.g. two installs of the same template).
**Problem:** `save()` always did `slug = Str::slug($this->name)`, overwriting the dedupe suffix (`-1`) → `UNIQUE constraint failed: workflow_definitions.slug`.
**Solution:** `Editor::resolveSlug` keeps the current slug when the name is unchanged; when it changes, generates a unique slug ignoring the current id.
**Prevents:** Slug collision when testing/saving workflows with duplicate names (common with reinstalled templates).

### L-006: Deferred Ideas need priority buckets, not a flat backlog (2026-07-20)

**Context:** Post-M7 STATE triage — flat deferred list mixed shipped work, M7 debts, and speculative polish.
**Problem:** Without P1/P2/P3 buckets, “next after M7” was ambiguous (OBS-06 vs billing providers looked equal).
**Solution:** Reclassify Deferred Ideas by urgency; archive items already absorbed by Features Completed / M6–M7.
**Prevents:** Planning noise and accidental revival of shipped work as open debt.

---

## Features Completed

| Feature              | Date       | Version | Status  |
| -------------------- | ---------- | ------- | ------- |
| studio-test-harness  | 2026-06-24 | 0.1.x   | ✅ Done |
| workflow-json-io     | 2026-06-24 | 0.1.x   | ✅ Done |
| workflow-code-bridge | 2026-06-24 | 0.1.x   | ✅ Done |
| workflow-queue-runner | 2026-07-01 | 0.2.x   | ✅ Done |
| multimodal-attachments (partial AMA) | 2026-06-30 | 0.1.2 | ✅ Done |
| workflow-cyclic-graphs (P0+P1) | 2026-06-30 | 0.2.x | ✅ Done |
| autonomous-multimodal-agents | 2026-07-02 | 0.2.x | ✅ Done |
| workflow-rag | 2026-07-02 | 0.2.x | ✅ Done |
| rag-knowledge-base-tool | 2026-07-02 | 0.2.x | ✅ Done |
| workflow-tool-approval | 2026-07-03 | 0.2.x | ✅ Done |
| workflow-token-streaming | 2026-07-03 | 0.2.x | ✅ Done |
| workflow-checkpoints-persistence | 2026-07-03 | 0.2.x | ✅ Done |
| workflow-parallel-execution | 2026-07-03 | 0.2.x | ✅ Done |
| stream-adapters | 2026-07-03 | 0.2.x | ✅ Done |
| unified-runs-and-traces | 2026-07-07 | 0.2.x | ✅ Done |
| cost-estimation | 2026-07-16 | 0.4.0 | ✅ Done |
| usage-analytics | 2026-07-16 | 0.5.0 | ✅ Done |
| usage-export-api | 2026-07-16 | 0.6.0 | ✅ Done |
| agent-tool-controls | 2026-07-17 | 0.7.x | ✅ Done |
| async-run-progress | 2026-07-17 | 0.7.x | ✅ Done |
| interpreted-parallel-concurrency | 2026-07-17 | 0.7.x | ✅ Done |
| external-observability | 2026-07-17 | 0.8.x | ✅ Done |
| agent-memory-controls | 2026-07-20 | 0.9.x | ✅ Done |
| context-engineering | 2026-07-21 | 0.9.x | ✅ Done |
| canvas-invoke-node | 2026-07-21 | 0.9.x → 0.10.0 | ✅ Done |

---

## Deferred Ideas

### P1 — M8 north star (performance / memory / context) — AD-021 / AD-022

Themes turned into specified features (AD-022 — shipped on `v0.9.x` / `v0.10.0`):

- [x] **Agent memory** → specified as [`agent-memory-controls`](../features/agent-memory-controls/spec.md) (AMC-01…05: `memory_config` envelope, compaction, summarizer, UI + node override)
- [x] **Context engineering** → specified as [`context-engineering`](../features/context-engineering/spec.md) (CTX-01…06: prompt assembly budgets for RAG/tool/state + truncation spans)
- [x] **Workflow/agent runtime quality** → absorbed by the two features above (token waste = unbudgeted context + silent history loss) + PTA below for concurrency correctness
- [x] **Tool approval inside parallel branches** → specified as [`parallel-tool-approval`](../features/parallel-tool-approval/spec.md) (P2 of M8; PTA-01…04)

### P2 — Valuable later (not M9 core)

- [x] **Canvas `invoke` / allowlisted hook node** — done: [`canvas-invoke-node`](../features/canvas-invoke-node/spec.md) shipped `v0.10.0`
- [ ] Dedicated Usage page / advanced charts / filters (beyond M5 minimal Dashboard)
- [ ] Multi-tenant / user attribution in usage
- [ ] Embeddings / RAG cost as a separate line item
- [ ] TraceDetail ↔ Inspector/Langfuse URL bridge — deep-link from Studio run to exporter UI

### P3 — Nice-to-have / polish / when-needed

- [ ] **Generic OpenTelemetry export** — OTLP spans from Neuron/Studio events; any backend (Tempo, Honeycomb, LangSmith-as-OTLP sink, etc.). **Not** a LangSmith-specific integration. Ship only when a host needs portable APM beyond Inspector/Langfuse
- [ ] **OBS-06** Settings status page (read-only) — confirm native/Inspector/Langfuse without reading `.env`
- [ ] Laravel Echo / `ShouldBroadcast` as async progress transport — SSE buffer already ships (M6)
- [ ] Billing provider integrations (Stripe, etc.) — host meters via UE today
- [ ] SO T12 loop hint; PE-08 join inspector preview; RAG hybrid/MMR — leftover polish from M2/M3
- [ ] Remove redundant layout `<link>` tags for bundle-inlined CSS (AD-001)
- [ ] Extract `StudioTestHarness.jsx` shell if composition grows further

### Dropped / not planned

- [x] **LangSmith-specific integration** — LangChain-centric; no PHP SDK; poor Neuron fit. Prefer generic OTel (P3) if a host must land in LangSmith UI (AD-021)

### Done / absorbed

- [x] **M5:** `usage-export-api` (UE-T1…T7) — shipped `v0.6.0`
- [x] **M5:** `usage-analytics` (UA-T1…T11) — shipped `v0.5.0`
- [x] Multi-turn / tool-round autonomy on agent node — Neuron already does; M6 exposes knobs + live SSE (`agent-tool-controls`)
- [x] Real-time SSE for `RunWorkflowJob` — M6 `async-run-progress` (buffer + SSE tail; Echo still deferred above)
- [x] **M7 Specify:** external monitoring Inspector + Langfuse (env-first) — Execute on `v0.8.x`
- [x] **M7 Execute:** OBS-01…05 (`ObservabilityManager`, Inspector wiring, Langfuse adapter, docs) — shipped `v0.8.0`

---

## Todos

- [x] `workflow-cyclic-graphs` P0 + P1 (T1–T19)
- [x] Docs T20–T21 + `docs/RELEASE.md` v0.2.x section
- [x] AMA-03–07, AMA-10
- [x] `workflow-rag` — KnowledgeBase + real executor + codegen + docs
- [x] AMA-09 — docs dedicated autonomous-agent guide sections
- [x] Rulesets / required status checks aligned with consolidated CI
- [x] **M4 `stream-adapters`** — SA-T10..SA-T13 (branch `feat/stream-adapters`; SA-T1..T8 ✅, SA-T9 partial, suite 278 green)
- [x] **Unified Runs and Traces** — T1–T7 complete (table unification, token tracking, unified API, 279 tests green)
- [x] Publish M1–M4 cycle (`v0.3.0` / `v0.3.1`) and absorb orphan tag into `main`
- [x] Open `v0.3.x` line and update ROADMAP/STATE/RELEASE
- [x] Absorb orphan tag `v0.3.2` into `main`
- [x] Release workflow: `RELEASE_TOKEN` + push `main` before tag (AD-012)
- [x] Secret `RELEASE_TOKEN` configured; `v0.3.3` published with commit in `main` ancestry
- [x] Specify M5 (Discuss → Spec) — AD-013; CE / UE / UA specs
- [x] Design M5 — AD-014; CE / UE / UA design.md
- [x] Tasks M5 — index + CE/UE/UA tasks.md (28)
- [x] Execute M5 `cost-estimation` (CE-T1…T13)
- [x] Execute M5 `usage-analytics` (UA-T1…T11, Pretty) — `v0.5.0`
- [x] Sync ROADMAP/STATE/RELEASE post-`v0.5.0` + open `v0.6.x` (AD-018)
- [x] Ruleset development lines `v*.*.x` (`apply-branch-rules.sh`)
- [x] Execute M5 `usage-export-api` (UE-T1…T7) on `v0.6.x`
- [x] Sync ROADMAP/STATE/RELEASE post-`v0.6.0` + open `v0.7.x` (AD-019)
- [x] Specify / design / tasks M6 (ATC + ARP + IPC)
- [x] Execute M6 `agent-tool-controls` → `async-run-progress` → `interpreted-parallel-concurrency` on `v0.7.x`
- [x] Specify M7 `external-observability` + AD-020 + ROADMAP/STATE (2026-07-17)
- [x] Design + tasks M7 `external-observability`
- [x] Execute M7 OBS-01…05 (`feat/external-observability`)
- [x] Release `v0.7.0` (M6) + open `v0.8.x` branch/line
- [x] Merge M7 → `v0.8.x` → release `v0.8.0`
- [x] AD-021: M8 = performance/memory/context; drop LangSmith; OTel → P3
- [x] Specify M8 (Discuss → feature specs) — AD-022; AMC / CTX / PTA specs
- [x] Design + tasks M8 — design inline in tasks (skipped as phase); 26 tasks, index in [m8-performance-memory-context/tasks.md](../features/m8-performance-memory-context/tasks.md)
- [x] Open `v0.9.x` from `main` (`v0.8.1`) for M8 Execute (AD-022)
- [x] Execute M8 `agent-memory-controls` (AMC-T1…T10) on `v0.9.x`
- [x] Execute M8 `context-engineering` (CTX-T1…T9) on `v0.9.x`
- [x] Execute M8: `parallel-tool-approval` on `v0.9.x`
- [x] Execute `canvas-invoke-node` (INV-T1…T6) on `v0.9.x`
- [x] Release `v0.9.0` (M8) + `v0.10.0` (`canvas-invoke-node`)
- [x] AD-023: open M9 on `v0.10.x` (Studio UX + RAG harden + neuron-ai)
- [ ] Merge M9 `feat/knowledge-base-rag` → `v0.10.x` (single PR)
- [ ] Release `v0.11.0` (or next minor) when M9 is stable on the line
