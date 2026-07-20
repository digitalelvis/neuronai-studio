# Context Engineering Specification

**Milestone:** M8 (P1) — [context](../m8-performance-memory-context/context.md) · **Tasks:** [tasks.md](./tasks.md)  
**Requirement IDs:** `CTX-xx` · **Date:** 2026-07-20

## Problem Statement

Everything that enters an agent prompt besides chat history is unbudgeted today: RAG chunks land in workflow state as `rag_context` and are interpolated verbatim by `StateTemplateInterpolator` into the agent message; tool results and large state fields are injected the same way, with no token limits and no truncation anywhere. A single oversized retrieval or tool output can blow the context window, waste tokens, or starve the history — and when content is dropped or should be dropped, nothing records it.

## Goals

- [ ] Enforce configurable token budgets for the three prompt-assembly inputs: RAG chunks (`rag_context`), tool results, and large state fields interpolated via `StateTemplateInterpolator`.
- [ ] Resolve budgets per agent (defaults in the `memory_config` envelope) with per-node overrides in agent-node `data` (M6 pattern).
- [ ] Record every truncation in trace span metadata — no silent context loss.

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Chat history window / compaction | [`agent-memory-controls`](../agent-memory-controls/spec.md) |
| Changing RAG retrieval itself (top-k, re-ranking, hybrid/MMR) | Retrieval quality is P3 polish; this feature budgets what was already retrieved |
| Semantic compression / LLM-based squeezing of RAG or tool output | Only mechanical truncation in M8; summarization is history-only (AMC) |
| System prompt / instructions budgeting | Author-controlled and short in practice |
| Provider-side context management (prompt caching, etc.) | Vendor concern |

**Context:** [m8-performance-memory-context/context.md](../m8-performance-memory-context/context.md)

---

## User Stories

### P1: Budget RAG chunks (`rag_context`) ⭐ MVP

**User Story**: As a workflow author using RAG, I want retrieved chunks capped at a token budget before interpolation so that big retrievals cannot blow the prompt.

**Why P1**: RAG is the largest uncontrolled prompt input today (chunks enter state verbatim).

**Acceptance Criteria**:

1. WHEN a RAG budget is configured and interpolated `rag_context` exceeds it THEN system SHALL truncate to fit the budget before the agent message is assembled, keeping whole chunks first and truncating the last included chunk only if needed.
2. WHEN truncating within a chunk THEN system SHALL cut at a sentence boundary within a configurable tolerance (Agent's Discretion default); only when no boundary exists inside the tolerance MAY it hard-cut, appending an explicit truncation marker.
3. WHEN `rag_context` fits the budget THEN content SHALL pass through byte-identical (no marker, no reflow).
4. WHEN no RAG budget is configured THEN behavior SHALL be today's (no truncation) — opt-in, zero regression.

**Independent Test**: State with `rag_context` of ~3 chunks / 2000 tokens + budget 800 → assembled message contains ≤ 800 tokens of RAG content, ends at a sentence boundary, carries a truncation marker.

---

### P1: Budget tool results ⭐ MVP

**User Story**: As a Studio author, I want oversized tool results capped before re-entering the model loop so that a verbose tool (scraper, SQL dump) doesn't consume the whole window.

**Why P1**: Tool results feed straight back into the prompt in Neuron's tool loop with no cap.

**Acceptance Criteria**:

1. WHEN a tool-result budget is configured and a tool result exceeds it THEN system SHALL truncate the result content to the budget with an explicit truncation marker before it is added to the prompt/history path.
2. WHEN the result is structured (JSON) THEN truncation SHALL apply to its serialized string form and the marker SHALL make clear the payload is partial.
3. WHEN no budget is configured THEN tool results pass through unchanged.
4. WHEN a truncated tool result is persisted (Eloquent thread) THEN the persisted message SHALL match what the model saw (truncated form), keeping history/prompt consistency.

**Independent Test**: Fake tool returning 50k chars + budget 1000 tokens → prompt and persisted message contain the truncated form with marker; SSE `tool_result` still emitted.

---

### P1: Budget state fields in interpolation ⭐ MVP

**User Story**: As a workflow author, I want large state fields interpolated into agent messages capped so that accumulated state (loop iterations, upstream outputs) doesn't grow prompts unbounded.

**Why P1**: `StateTemplateInterpolator` inserts any state field verbatim; loops make this grow unbounded.

**Acceptance Criteria**:

1. WHEN a state-field budget is configured and an interpolated field's value exceeds it THEN `StateTemplateInterpolator` output SHALL contain the truncated value with a marker.
2. WHEN a template interpolates multiple fields THEN the budget SHALL apply per field (not to the concatenated message), so one huge field cannot starve the others.
3. WHEN `rag_context` is interpolated THEN the RAG budget (CTX-01) SHALL take precedence over the generic state-field budget for that field (no double truncation).
4. WHEN no budget is configured THEN interpolation is unchanged.

**Independent Test**: Template `"Summarize: {{big_field}} using {{small_field}}"` with `big_field` over budget → output has truncated `big_field` + intact `small_field`.

---

### P1: Per-agent defaults + per-node overrides ⭐ MVP

**User Story**: As a Studio author, I want to set budgets once per agent and override them per node so that budget policy follows the same mental model as tool controls and memory.

**Why P1**: Without resolution + UI, budgets are dead config like `memory_config` was.

**Acceptance Criteria**:

1. WHEN budgets are set in the agent's `memory_config` envelope THEN they SHALL apply to every visit of that agent (playground and workflow nodes).
2. WHEN an agent node sets budget overrides in node `data` THEN those SHALL win for that node visit (M6 `tool_max_runs` pattern); empty override = inherit.
3. WHEN budgets are edited in the Studio UI (agent form section + node inspector) THEN values SHALL round-trip and invalid values (≤ 0, non-int) SHALL be rejected with field-level validation.
4. WHEN nothing is configured anywhere THEN all three budget types SHALL be disabled (pass-through), preserving current behavior.

**Independent Test**: Agent default RAG budget 800, node override 200 → node visit truncates to 200; playground run truncates to 800.

---

### P1: Truncation observability in trace spans ⭐ MVP

**User Story**: As an operator debugging quality issues, I want every truncation recorded in the run's trace spans so that I can tell when the model didn't see full context.

**Why P1**: Locked decision — budgets without observability create silent context loss, the exact failure mode M8 exists to remove.

**Acceptance Criteria**:

1. WHEN any budget truncates content THEN the active trace span SHALL record metadata: input kind (`rag_context` / `tool_result` / `state_field`), field/tool name, tokens before/after (estimate), and truncation strategy applied.
2. WHEN no truncation occurs THEN no truncation metadata SHALL be written (no noise).
3. WHEN native tracing is disabled (`NEURONAI_STUDIO_NATIVE_TRACING=false`) THEN truncation SHALL still apply, without error, with metadata skipped.
4. WHEN the Debugger renders a span with truncation metadata THEN the metadata SHALL be visible in the span detail (existing metadata rendering is sufficient; no new UI component required).

**Independent Test**: Run with forced RAG truncation → `StudioTraceSpan` for the agent step carries truncation metadata with before/after token counts.

---

### P2: Codegen + docs

**User Story**: As a host exporting workflows, I want budgets reflected in generated code and documented.

**Acceptance Criteria**:

1. WHEN codegen emits agent/workflow setup and budgets are configured THEN it SHALL include them when expressible; otherwise emit a documented comment.
2. Docs SHALL update `guides/workflows/node-types/ai-nodes.md`, `guides/agents/creating-agents.md`, `guides/workflows/state-and-conditions.md`, `guides/workflows/runtime-and-traces.md`, `reference/configuration.md`.

---

## Edge Cases

- WHEN a budget is smaller than a single RAG chunk THEN system SHALL include one truncated chunk (never emit zero context while `rag_context` is non-empty).
- WHEN a budget is smaller than the truncation marker itself THEN system SHALL emit only the marker (degenerate floor; validation should prevent absurd budgets, but runtime must not error).
- WHEN sentence-boundary tolerance finds no boundary (e.g. minified JSON, base64 blob) THEN hard cut at the budget with marker — never loop or error.
- WHEN state field value is non-string (array/object) THEN budget applies to its serialized form, consistent with current interpolation semantics.
- WHEN both AMC compaction and CTX budgets apply in the same run THEN budgets apply at assembly time first; history compaction operates on the (already truncated) persisted messages — no double counting.
- WHEN multibyte content (pt-BR, emoji) is truncated THEN cut SHALL respect character boundaries (no broken UTF-8 sequences).
- WHEN a node override sets a budget but the agent has none THEN the override alone SHALL activate budgeting for that visit.

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| CTX-01 | P1: Budget RAG chunks | Tasks | Pending |
| CTX-02 | P1: Budget tool results | Tasks | Pending |
| CTX-03 | P1: Budget state fields | Tasks | Pending |
| CTX-04 | P1: Defaults + overrides + UI | Tasks | Pending |
| CTX-05 | P1: Truncation observability | Tasks | Pending |
| CTX-06 | P2: Codegen + docs | Tasks | Pending |

**Coverage:** 6 total, 6 mapped to tasks ([tasks.md](./tasks.md)), 0 unmapped

---

## Success Criteria

- [ ] A workflow with oversized RAG + verbose tool + big state field completes with each input at/under its budget and truncations visible in span metadata.
- [ ] With no budgets configured, prompt assembly is byte-identical to today (suite stays green).
- [ ] Budgets configurable per agent and per node in Studio, override wins.
- [ ] RAG truncation ends at sentence boundaries within tolerance in tests with prose content.
