# M8 Performance, Memory & Context — Context

**Gathered:** 2026-07-20  
**Milestone:** M8 — Agent & workflow performance (memory, context engineering, runtime quality)  
**Status:** Specified (AD-021 north star, AD-022 feature split). Design inline in tasks — Execute next on `v0.9.x`.  
**Decisions:** [STATE.md AD-021 / AD-022](../../project/STATE.md)  
**Tasks index:** [tasks.md](./tasks.md)  
**Specs:** [`agent-memory-controls`](../agent-memory-controls/spec.md) · [`context-engineering`](../context-engineering/spec.md) · [`parallel-tool-approval`](../parallel-tool-approval/spec.md)

---

## Feature Boundary

M8 delivers **agent & workflow performance**: durable, controllable agent memory (activate `memory_config` with window / driver / summarization-by-compaction), **full context engineering** (prompt assembly budgets for history + RAG chunks + tool results + large state fields, with truncation observability), and — as P2 — **tool approval inside parallel branches** (pause/resume instead of fail-closed). It does not deliver more monitoring vendors, Settings polish, or canvas `invoke`.

MVP scope = **"1C + prompt assembly"**: the two P1 features (`agent-memory-controls`, `context-engineering`) including summarization. Context engineering covers the whole prompt assembly path — not just chat history.

---

## Implementation Decisions (locked at Discuss, 2026-07-20)

### Milestone scope & split (AD-022)

- Three named features, Execute order: `agent-memory-controls` (P1, AMC-xx) → `context-engineering` (P1, CTX-xx) → `parallel-tool-approval` (P2, PTA-xx).
- PTA is specified now but executed only after both P1 features.
- LangSmith stays dropped; generic OTel export stays P3 (AD-021).

### Memory: compaction, not silent deletes

- Today Neuron's `HistoryTrimmer` physically deletes trimmed messages on the Eloquent path. That silent-delete behavior is **superseded**: when history exceeds its budget, the trimmed prefix is replaced by a **persisted summary message** in the Eloquent thread (compaction).
- Summarizer = **dedicated configurable cheap model** (own provider/model config keys), with fallback to the agent's own provider/model when not configured.
- If summarization fails entirely, fail-safe = non-destructive trim (exclude from prompt, keep rows) — never silent deletion.

### Granularity: `memory_config` envelope + per-node override

- Per-agent settings live in the existing (currently dead) `AgentDefinition.memory_config` JSON column as a **single envelope**: context window, driver (Eloquent vs in-memory), summarization on/off + thresholds, budgets.
- Per-node override in agent-node `data` on the canvas, following the M6 pattern used for `tool_max_runs` / `parallel_tool_calls`.

### Context engineering: prompt assembly budgets

- Token limits / truncation for RAG chunks (`rag_context`), tool results, and large state fields interpolated via `StateTemplateInterpolator` into agent messages.
- Per-agent defaults + per-node overrides (same envelope/override pattern).
- Every truncation is recorded in trace span metadata (native Debugger) — no silent context loss.

### Parallel tool approval (P2)

- Catch `ToolApprovalRequiredException` in `ForkNodeExecutor` / concurrent scheduler; pause with a **parallel checkpoint** mirroring the Human-in-branch pattern (lesson L-003 in STATE.md).
- Resume approve/reject per branch; parity between sequential and Amp concurrent scheduling.

### Version line (AD-022)

- M8 Execute targets a new **`v0.9.x`** feature line; `v0.8.x` becomes the patch line. Branch is opened later, at Execute.

### Agent's Discretion

- Exact config key names inside the `memory_config` envelope and in `config/neuronai-studio.php` (summarizer provider/model keys, budget keys).
- Summary message format and role (e.g. system vs assistant, prefix marker) — must round-trip through `EloquentChatHistory` and be visibly distinguishable in the thread.
- Token estimation method for budgets (reuse Neuron's estimator vs chars/4 heuristic) — must be consistent between trim decision and budget enforcement.
- Truncation strategy details (head/tail keep ratios, sentence-boundary tolerance for RAG chunks).
- Span metadata schema for truncation events.
- UI layout of the memory/budget sections on the agent form and node inspector.

---

## Specific References

- M6 override pattern: `tool_max_runs` / `parallel_tool_calls` — columns/casts on `AgentDefinition` + agent-node `data` override ([agent-tool-controls](../agent-tool-controls/spec.md)).
- Fork resume pattern for branches: L-003 in [STATE.md](../../project/STATE.md) (skip completed, resume pending, run not-yet-started).
- Existing pause/resume for tool approval outside forks: `WorkflowRunner::pauseForToolApproval` / `resumeToolApproval`, status `awaiting_tool_approval`, SSE `tool_approval_required` / `tool_approval_resolved`.

## Starting points in codebase

- `DynamicAgent::chatHistory()` (src/Runtime/DynamicAgent.php) — `InMemoryChatHistory` when no thread id; `EloquentChatHistory` on `StudioChatMessage` when thread id set; window from constructor override else `config('neuronai-studio.chat_history_context_window')` (default 150000, env `NEURONAI_STUDIO_CHAT_HISTORY_CONTEXT_WINDOW`).
- `AgentDefinition.memory_config` — JSON column, cast to array, fillable, copied by `TemplateInstaller`, **not read at runtime** today.
- Thread wiring: playground `AgentRunner::stream()` uses scoped key `agent:{id}:{uuid}`; workflow agent nodes (`AgentNodeExecutor` via `runInline`/`streamInline`/`structuredInline`) use `$run->thread_id` set by `WorkflowRunner` as `__studio_thread_id`; `ChatThreadLoader` handles both.
- RAG → prompt: workflow state `rag_context` interpolated by `StateTemplateInterpolator` into the agent message — not merged into chat history. No truncation exists for RAG chunks, tool results, or state fields.
- Fork gap: `ForkNodeExecutor::runBranch` catches only `HumanInputRequiredException` → `ParallelBranchInterruptException` (reason: human); `ToolApprovalRequiredException` is not caught — on the Amp path an uncaught throwable cancels other futures and fails the run.
- Parallel config: `parallel.concurrency` env `NEURONAI_STUDIO_PARALLEL_CONCURRENCY` default `concurrent`.

---

## Deferred Ideas (explicitly out of M8)

- Generic OpenTelemetry export — P3 when-needed (AD-021).
- OBS-06 read-only Settings status page — P3.
- Canvas `invoke` / allowlisted hook node — P2, not M8 core.
- Laravel Echo / `ShouldBroadcast` transport for async progress — P3.
- LangSmith-specific integration — dropped (AD-021).
