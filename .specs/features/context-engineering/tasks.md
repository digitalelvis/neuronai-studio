# Context Engineering — Tasks

**Spec**: [spec.md](./spec.md) · **Context**: [../m8-performance-memory-context/context.md](../m8-performance-memory-context/context.md)  
**Status**: Done — Execute on `v0.9.x` after AMC  
**Linha**: `v0.9.x` · **Ordem M8**: 2/3  
**Design**: skipped — inline design decisions noted per task.

---

## Execution Plan

```
CTX-T1 → CTX-T3 [P]
       → CTX-T4 [P]
       → CTX-T5 [P]
CTX-T2 (needs AMC-T1/T2) ─→ CTX-T3/T4/T5
CTX-T3, CTX-T4, CTX-T5 → CTX-T6 → CTX-T7 → CTX-T8 [P] → CTX-T9
```

Cross-feature dependency: CTX-T2 reuses the `memory_config` envelope + node-override resolution shipped by AMC-T1/AMC-T2.

---

### CTX-T1 — Truncation service (CTX-01, CTX-02, CTX-03)

**What**: Token-budget truncation utility: estimate tokens, cut to budget, sentence-boundary tolerance for prose, hard cut fallback, UTF-8-safe, explicit truncation marker; returns before/after estimates for observability.  
**Where**: new `src/Runtime/Context/TokenBudgetTruncator.php` (or similar)  
**Depends on**: None  
**Reuses**: token estimation approach consistent with AMC/history trimming (spec consistency requirement)  
**Inline design**: tolerance default, marker text, head/tail keep ratio = agent's discretion (context.md).  
**Done when**:

- [ ] Prose cut ends at sentence boundary within tolerance; no boundary → hard cut + marker
- [ ] Under-budget input returned byte-identical; degenerate budgets (≤ marker size) don't error
- [ ] Multibyte content never produces broken UTF-8

**Tests**: unit (prose, JSON, base64, multibyte, degenerate budgets)  
**Gate**: quick

---

### CTX-T2 — Budget keys in envelope + resolution (CTX-04)

**What**: Add budget keys (RAG, tool result, state field) to the `memory_config` envelope and node-override resolution; nothing configured = budgets disabled.  
**Where**: `MemoryConfig` envelope (AMC-T1), `AgentRunner` / `AgentNodeExecutor` resolution (AMC-T2)  
**Depends on**: AMC-T1, AMC-T2  
**Reuses**: AMC envelope + override machinery  
**Done when**:

- [ ] Resolution matrix: none / agent default / node override (override wins; override alone activates budgeting)
- [ ] Invalid budgets (≤ 0, non-int) rejected by validation

**Tests**: unit (resolution matrix)  
**Gate**: quick

---

### CTX-T3 — RAG chunk budget on `rag_context` (CTX-01) [P]

**What**: Apply the RAG budget to `rag_context` before/at interpolation: keep whole chunks first, truncate last included chunk at sentence boundary.  
**Where**: `StateTemplateInterpolator` / agent message assembly in `AgentRunner`  
**Depends on**: CTX-T1, CTX-T2  
**Reuses**: `TokenBudgetTruncator`  
**Inline design**: chunk delimiter detection (how `rag_context` joins chunks today) = confirm at implementation; RAG budget takes precedence over generic state budget for this field.  
**Done when**:

- [ ] Over-budget `rag_context` → ≤ budget, whole-chunks-first, marker present
- [ ] Budget smaller than one chunk → one truncated chunk (never zero context)
- [ ] No budget → byte-identical pass-through

**Tests**: feature (workflow with seeded rag_context)  
**Gate**: quick

---

### CTX-T4 — State-field budget in interpolation (CTX-03) [P]

**What**: Per-field budget in `StateTemplateInterpolator`: truncate each interpolated field independently; non-string values budgeted on serialized form.  
**Where**: `src/Runtime/StateTemplateInterpolator.php`  
**Depends on**: CTX-T1, CTX-T2  
**Reuses**: `TokenBudgetTruncator`  
**Done when**:

- [ ] Per-field application (huge field can't starve others); `rag_context` excluded (handled by CTX-T3)
- [ ] Arrays/objects budgeted on serialized form

**Tests**: unit (multi-field templates)  
**Gate**: quick

---

### CTX-T5 — Tool-result budget (CTX-02) [P]

**What**: Cap tool results to budget before they re-enter the prompt/history path; persisted message matches what the model saw.  
**Where**: agent tool-loop integration point in `AgentRunner` / `DynamicAgent` (where tool results are observed/persisted)  
**Depends on**: CTX-T1, CTX-T2  
**Reuses**: `TokenBudgetTruncator`; live tool SSE from `agent-tool-controls` (payload unchanged, content truncated)  
**Inline design**: interception point on the Neuron tool loop = agent's discretion (observer vs history wrapper); must not bypass approval semantics.  
**Done when**:

- [ ] Oversized tool result truncated with marker in prompt and in persisted `StudioChatMessage`
- [ ] SSE `tool_result` still emitted; no budget → unchanged

**Tests**: feature (fake verbose tool)  
**Gate**: quick

---

### CTX-T6 — Truncation span metadata (CTX-05)

**What**: Record every truncation in the active trace span: kind (`rag_context`/`tool_result`/`state_field`), field/tool name, tokens before/after, strategy.  
**Where**: truncation call sites → `TelemetryTracker` / `StudioTraceSpan` metadata  
**Depends on**: CTX-T3, CTX-T4, CTX-T5  
**Reuses**: compaction metadata pattern (AMC-T7)  
**Done when**:

- [ ] Metadata written on truncation only; visible in Debugger span detail (existing rendering)
- [ ] Native tracing off → truncation still applies, no error

**Tests**: feature  
**Gate**: quick

---

### CTX-T7 — UI: budgets on agent form + node inspector (CTX-04)

**What**: Budget fields in the agent form memory/budgets section and agent-node inspector overrides; rebuild bundles.  
**Where**: agent Livewire form, canvas inspector (JS), bundles  
**Depends on**: CTX-T2, AMC-T8, AMC-T9  
**Reuses**: AMC UI sections (extend, don't duplicate)  
**Done when**:

- [ ] Round-trip save/load; field-level validation; empty = disabled/inherit
- [ ] Bundles rebuilt (IIFE, AD-001)

**Tests**: feature (Livewire) + manual canvas check  
**Gate**: build

---

### CTX-T8 — Codegen (CTX-06) [P]

**What**: Emit budgets in generated code when expressible; documented comment otherwise.  
**Where**: codegen templates  
**Depends on**: CTX-T3, CTX-T4, CTX-T5  
**Done when**:

- [ ] Snapshot contains budget setup when configured

**Tests**: codegen snapshot  
**Gate**: quick

---

### CTX-T9 — Docs (CTX-06)

**What**: Document budgets, truncation semantics, and span metadata.  
**Where**: `docs/guides/workflows/node-types/ai-nodes.md`, `docs/guides/agents/creating-agents.md`, `docs/guides/workflows/state-and-conditions.md`, `docs/guides/workflows/runtime-and-traces.md`, `docs/reference/configuration.md`  
**Depends on**: CTX-T7, CTX-T8  
**Done when**:

- [ ] Docs rows from the ROADMAP M8 index updated

**Tests**: none  
**Gate**: docs
