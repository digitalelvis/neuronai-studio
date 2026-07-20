# M8 Performance, Memory & Context — Context

**Gathered:** 2026-07-20  
**Milestone:** M8 — Agent & workflow performance  
**Status:** Planning (AD-021). Specify next — feature split TBD.  
**Decision:** [STATE.md AD-021](../../project/STATE.md)

---

## North star

After M7 observability, prioritize **how well agents and workflows use the model**:

1. **Performance** — fewer wasted tokens/round-trips; correct behavior under concurrency (e.g. tool approval in parallel branches)
2. **Memory** — durable, controllable chat history / thread memory (`memory_config`, Eloquent vs in-memory, context window)
3. **Context engineering** — deliberate control of what enters the prompt (state, RAG, tools); budgets, truncation, summarization

Not in M8 core: more monitoring vendors, Settings polish, canvas `invoke` (unless Specify proves it unblocks context workflows).

---

## Explicitly out

| Item | Disposition |
|------|-------------|
| LangSmith-specific integration | **Dropped** (AD-021) — LangChain-centric; no PHP SDK |
| Generic OpenTelemetry export | **P3 / when-needed** — portable OTLP later; not M8 |
| OBS-06 Settings status | P3 |
| Langfuse / Inspector | Already shipped in M7 |

---

## Starting points in codebase

- `DynamicAgent::chatHistory()` — `InMemoryChatHistory` / `EloquentChatHistory` + `chat_history_context_window`
- `AgentDefinition.memory_config`
- RAG → state → agent via `StateTemplateInterpolator` / `rag_context`
- M6 knobs: `tool_max_runs`, `parallel_tool_calls`; gap: tool approval inside parallel branches

---

## Next step

Discuss → Specify: turn the P1 themes in STATE Deferred Ideas into named features with IDs, then Design/Tasks. Prefer English feature docs.
