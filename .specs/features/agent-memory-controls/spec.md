# Agent Memory Controls Specification

**Milestone:** M8 (P1) — [context](../m8-performance-memory-context/context.md) · **Tasks:** [tasks.md](./tasks.md)  
**Requirement IDs:** `AMC-xx` · **Date:** 2026-07-20

## Problem Statement

`AgentDefinition.memory_config` exists as a JSON column (cast, fillable, copied by `TemplateInstaller`) but is **never read at runtime** — every agent gets the same global window from `config('neuronai-studio.chat_history_context_window')` (default 150000). Worse, when history exceeds the window, Neuron's `HistoryTrimmer` **physically deletes** the oldest messages from the Eloquent thread: long-running threads silently lose durable history with no summary and no trace. Operators cannot tune memory per agent or per node, and there is no summarization anywhere in the stack.

## Goals

- [ ] Activate `memory_config` as the single per-agent memory envelope (context window, driver, summarization settings) read at runtime by `DynamicAgent` / `AgentRunner`.
- [ ] Replace silent-delete trimming with **compaction**: trimmed prefix becomes a persisted summary message in the Eloquent thread.
- [ ] Ship a summarizer service with a dedicated configurable cheap model, falling back to the agent's own provider/model.
- [ ] Expose memory controls in the Studio agent form and as per-node overrides on the agent node (M6 `tool_max_runs` pattern).

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Prompt assembly budgets (RAG/tool/state) | [`context-engineering`](../context-engineering/spec.md) |
| Semantic / vector long-term memory | Different capability; not in AD-022 |
| Cross-thread / cross-agent shared memory | Threads remain the memory unit |
| Replacing Neuron's `ChatHistoryInterface` contract | Studio extends behavior, keeps vendor contract |
| Summary editing UI in threads | Read-only display is enough for M8 |

**Context:** [m8-performance-memory-context/context.md](../m8-performance-memory-context/context.md)

---

## User Stories

### P1: Configure agent memory via `memory_config` ⭐ MVP

**User Story**: As a Studio author, I want to set context window, history driver, and summarization behavior per agent so that each agent's memory fits its use case instead of one global default.

**Why P1**: The column is dead config today; nothing else in M8 works without the envelope being read at runtime.

**Acceptance Criteria**:

1. WHEN `AgentDefinition.memory_config` contains a context window (int > 0) THEN `DynamicAgent::chatHistory()` SHALL use it instead of `config('neuronai-studio.chat_history_context_window')`.
2. WHEN `memory_config` sets the driver to `in_memory` THEN the agent SHALL use `InMemoryChatHistory` even when a thread id is set (no rows persisted); WHEN driver is `eloquent` or absent THEN current behavior SHALL be preserved (`EloquentChatHistory` on `StudioChatMessage` when thread id set, `InMemoryChatHistory` otherwise).
3. WHEN `memory_config` is null or empty THEN runtime behavior SHALL be byte-for-byte today's behavior (global window, driver by thread presence) — zero regression for existing agents.
4. WHEN an agent node sets memory overrides in node `data` THEN those values SHALL win over the agent's `memory_config` for that node visit, following the M6 `tool_max_runs` / `parallel_tool_calls` override pattern.
5. WHEN `memory_config` contains invalid values (window ≤ 0, unknown driver, malformed thresholds) THEN save SHALL be rejected with a validation error (Livewire error / 422), never silently coerced at runtime.

**Independent Test**: Save agent with `memory_config.context_window = 4000` → run in playground → `DynamicAgent` history resolves window 4000 (assert via history instance); agent without `memory_config` behaves exactly as before.

---

### P1: Compaction on trim — persisted summary, no silent deletes ⭐ MVP

**User Story**: As an operator running long-lived threads, I want old turns replaced by a persisted summary message when the window is exceeded so that the thread keeps durable, useful memory instead of silently losing history.

**Why P1**: This is the AD-022 core decision — silent-delete `HistoryTrimmer` behavior is superseded.

**Acceptance Criteria**:

1. WHEN summarization is enabled and history exceeds the configured budget/threshold THEN the system SHALL summarize the trimmed prefix and persist one summary message in the Eloquent thread replacing the trimmed messages, and the assembled prompt SHALL contain summary + retained suffix within the window.
2. WHEN compaction runs THEN the trimmed original messages SHALL NOT remain in the active prompt path, and the summary message SHALL be distinguishable from ordinary messages (role/marker per Agent's Discretion) both in DB and in the thread UI.
3. WHEN summarization is disabled (or driver is in-memory with no thread) THEN trimming SHALL be **non-destructive**: messages beyond the window are excluded from the prompt but SHALL NOT be deleted from `StudioChatMessage` rows (supersedes today's physical delete).
4. WHEN a thread already contains a summary message and the window is exceeded again THEN the system SHALL roll the summary forward (previous summary + newly trimmed messages → new summary), keeping at most one active summary per thread.
5. WHEN compaction happens during a run THEN a trace span (or span metadata) SHALL record that compaction occurred (message count summarized, token estimate before/after).

**Independent Test**: Seed a thread with messages exceeding a tiny window (e.g. 500 tokens), run one chat turn → thread now has 1 summary message + recent suffix, older rows compacted, prompt under budget, span metadata records the compaction.

---

### P1: Summarizer service with dedicated cheap model ⭐ MVP

**User Story**: As a host operator, I want summarization to run on a cheap dedicated model so that compaction doesn't burn the agent's (expensive) model budget.

**Why P1**: Compaction is only viable in production if its cost is controlled.

**Acceptance Criteria**:

1. WHEN summarizer provider/model config keys are set (in `config/neuronai-studio.php`, env-backed) THEN the summarizer service SHALL use them for all compaction calls.
2. WHEN summarizer keys are NOT configured THEN the service SHALL fall back to the agent's own provider/model.
3. WHEN the summarizer call fails (provider error, timeout, model unavailable) THEN the system SHALL fall back in order: dedicated model → agent's model → **non-destructive trim** (exclude from prompt, keep rows, no summary), and the run SHALL complete without error; the fallback SHALL be recorded in span metadata.
4. WHEN summarization runs THEN its token usage SHALL be metered like other LLM calls (usage/cost pipeline from M5) attributed to the run.

**Independent Test**: Configure a fake summarizer provider that throws → run compaction scenario → run completes, no rows deleted, span metadata shows `summarizer_fallback: trim`.

---

### P1: Studio UI — agent form + node override ⭐ MVP

**User Story**: As a Studio author, I want memory controls on the agent form and on the agent node inspector so that I can configure memory without touching JSON or env.

**Why P1**: M8 completion criterion requires Studio to expose window/driver/summarization per agent and per node.

**Acceptance Criteria**:

1. WHEN editing an agent THEN the form SHALL expose context window, driver, and summarization on/off + thresholds, persisting into the `memory_config` envelope, and values SHALL round-trip (save → reload → same values).
2. WHEN editing an agent node on the canvas THEN the inspector SHALL expose the same override fields in node `data` (empty = inherit from agent), mirroring the M6 override UX.
3. WHEN override fields are invalid (window ≤ 0, thresholds out of range) THEN the UI SHALL block save with a field-level validation message.
4. WHEN fields are untouched THEN saving SHALL NOT write a `memory_config` envelope (null stays null) — installed templates and existing agents remain unchanged.

**Independent Test**: Set window/driver/summarization on the agent form, reload → values persist; set node override → node visit uses override (assert via runtime test from story 1).

---

### P2: Codegen + docs

**User Story**: As a host exporting workflows/agents, I want generated code and docs to reflect memory settings so that exported artifacts behave like Studio runs.

**Acceptance Criteria**:

1. WHEN codegen emits agent bootstrap and `memory_config` is set THEN generated code SHALL include the equivalent history/window setup when expressible; non-expressible settings SHALL be emitted as a documented comment.
2. Docs SHALL update `guides/agents/creating-agents.md`, `guides/agents/playground-and-threads.md`, `guides/workflows/node-types/ai-nodes.md`, `reference/configuration.md`, `reference/database-schema.md` (memory_config no longer "reserved for future").

---

## Edge Cases

- WHEN the budget is smaller than a single message (one message alone exceeds the window) THEN system SHALL keep that latest message in the prompt (never produce an empty prompt) and skip compaction for it, recording the over-budget condition in span metadata.
- WHEN driver is `in_memory` and summarization is enabled THEN compaction SHALL apply in-session only (summary message lives in the in-memory history, nothing persisted) — no error.
- WHEN a thread is shared across node visits in one workflow run (`__studio_thread_id`) and two visits both trigger compaction THEN the second compaction SHALL see the first's summary (single active summary invariant holds).
- WHEN the summary message itself would exceed the window THEN system SHALL truncate the summary rather than recurse into summarizing summaries indefinitely.
- WHEN `memory_config` from an installed template contains keys unknown to the envelope THEN unknown keys SHALL be ignored (forward compatibility), known keys validated.
- WHEN resuming a paused run (HITL / tool approval) after a compaction THEN the restored history SHALL include the summary message (compaction survives checkpoint/resume).

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| AMC-01 | P1: Configure memory via `memory_config` | Execute | Done (T1–T3 ✅) |
| AMC-02 | P1: Compaction on trim | Execute | In progress (T5–T6 ✅) |
| AMC-03 | P1: Summarizer service | Execute | Done (T4 ✅) |
| AMC-04 | P1: Studio UI + node override | Tasks | Pending |
| AMC-05 | P2: Codegen + docs | Tasks | Pending |

**Coverage:** 5 total, 5 mapped to tasks ([tasks.md](./tasks.md)), 0 unmapped

---

## Success Criteria

- [ ] A thread seeded past its budget ends a run with 1 persisted summary + suffix, prompt under budget, zero silently deleted rows.
- [ ] Agent form and node inspector round-trip window/driver/summarization; node override wins at runtime.
- [ ] Summarizer failure degrades to agent model, then to non-destructive trim — run never fails because of memory management.
- [ ] Agents without `memory_config` show zero behavior change (existing test suite stays green).
