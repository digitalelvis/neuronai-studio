# State

**Last Updated:** 2026-07-02
**Development line:** `v0.2.x` (target release `v0.2.0`)
**Latest published:** `v0.1.2` on `main`
**Current Work:** Sprint M1 fechada — preparar release `v0.2.0` (PR `v0.2.x` → `main`)

---

## Recent Decisions (Last 60 days)

### AD-004: Linha de desenvolvimento v0.2.x (2026-06-30)

**Decision:** Abrir `v0.2.x` a partir de `main` (`v0.1.2`) para o milestone M1 (north star: agentes multimodais autônomos + grafos cíclicos).
**Reason:** `v0.1.x` entregou fundação do Studio (harness, code bridge, multimodal parcial); ciclos e RAG real exigem minor bump.
**Trade-off:** `v0.0.x` permanece como linha histórica; novos PRs vão para `v0.2.x`.
**Impact:** Ver [ROADMAP.md](ROADMAP.md); primeiro entregável = nó `loop` + validação de ciclos.

### AD-003: Roadmap north star — cíclicos + multimodal autônomo (2026-06-30)

**Decision:** Priorizar M1 com três features P0 (`workflow-cyclic-graphs`, `autonomous-multimodal-agents`, `workflow-rag`) antes de P1/P2.
**Reason:** Estado atual é DAG-only, `RagNodeExecutor` stub, `GraphExecutionLoop` sem guardrail — bloqueia agentes autônomos com mídia em loops.
**Trade-off:** Nove features planejadas aumentam superfície; M1 é mínimo viável para north star.
**Impact:** Ver [.specs/project/ROADMAP.md](ROADMAP.md).

### AD-001: IIFE output for studio JS bundles (2026-06-24)

**Decision:** Build `workflow-canvas.bundle.js` and `studio-chat.bundle.js` as IIFE (`NeuronAIStudioCanvas`, `NeuronAIStudioChat`).
**Reason:** Both bundles ship React with overlapping minified top-level `const` names; loading both on workflow editor caused `Identifier 'fo' has already been declared`.
**Trade-off:** Slightly larger bundles; CSS now injected via JS instead of separate `.css` files from Vite.
**Impact:** Workflow editor loads canvas + chat without global scope collision; `window.mountStudioChat` available for Test tab.

### AD-002: POST SSE for workflow runs and human resume (2026-06-24)

**Decision:** Workflow test harness uses POST stream endpoints with checkpoint/resume for Human nodes.
**Reason:** Supports attachments, context payload, and conversational resume without modals.
**Trade-off:** Breaking change from GET workflow run stream.
**Impact:** `HumanNodeExecutor` throws `HumanInputRequiredException`; `WorkflowRunner` persists checkpoint with `awaiting_input` status.

---

## Active Blockers

- Nenhum blocker ativo para `v0.2.0`.

---

## M1 progress snapshot

| Feature | Status | Notas |
|---------|--------|-------|
| `workflow-cyclic-graphs` | ✅ done | P0 + P1 entregues |
| `autonomous-multimodal-agents` | ✅ done | AMA-09 docs entregue |
| `workflow-rag` | ✅ done | Fatia 1–3 (backend, UI, codegen, docs) |

### workflow-rag — Fatia 1 (backend) entregue

- [x] Migrations `knowledge_bases` + `knowledge_documents`
- [x] Models `KnowledgeBase` (defaults provider/model/driver) + `KnowledgeDocument` (status ingest)
- [x] Config `rag`: drivers vector store, providers/modelos embeddings, retrieval + chunk defaults
- [x] `EmbeddingsFactory` + `VectorStoreFactory` extensíveis (`extend()`), default `file`/`openai`
- [x] `DocumentIngestService` (load → split → embed → persist + status) e `RagRetrievalService` (top_k, threshold, `toContext`)
- [x] `RagNodeExecutor` real → `rag_context` {query, results, context, top_score}
- [x] `StateTemplateInterpolator` com dot notation (`{{rag_context.context}}`)
- [x] 15 testes novos (ingest, retrieval, executor, interpolator) — suíte 203 verde
- [x] Fatia 2: CRUD Studio (`KnowledgeBases\Index`/`Edit`) + ingest UI (upload + texto) + `RagFields` inspector no canvas + debug search (`KnowledgeBaseSearchController`) + exposição KBs ao canvas + nav link
- [x] Fatia 2: 10 testes novos (CRUD/ingest/preview, search controller, exposição canvas) — suíte 213 verde
- [x] `rag-knowledge-base-tool` — aba RAG em tools/create, `KnowledgeBaseTool`, `ToolResolver` branch `type: rag`
- [x] Fatia 3: `RagNodeCodeGenerator` + docs

## M2 progress snapshot

| Feature | Status | Notas |
|---------|--------|-------|
| `workflow-structured-output` | ✅ done | T1–T17 ✅; T12 parcial — hint dot notation só no condition (loop sem inspector) |
| `workflow-tool-approval` | ⏳ planned | — |
| `workflow-token-streaming` | ⏳ planned | — |

### Structured output — entregue

- [x] `structured_output_scan_paths`, `OutputClassRegistry`, `StructuredOutputResolver`
- [x] `WorkflowStateValue` + dot notation em condition/loop
- [x] `AgentRunner::structuredInline` + branch structured em `LlmNodeExecutor` / `AgentNodeExecutor`
- [x] `StructuredOutputValidationException` + `validation_errors` no SSE/trace
- [x] Canvas inspector (T10–T13), round-trip T16, codegen T14–T15, docs T17
- [ ] T12 parcial — hint `lead.tier` no condition; loop aguarda inspector M1

## M3 progress snapshot

| Feature | Status | Notas |
|---------|--------|-------|
| `workflow-queue-runner` | ✅ done | T1–T11 ✅ — `RunWorkflowJob`, `ResumeWorkflowJob`, async run/resume API, polling, docs |

### Queue runner — entregue

- [x] `async_runs_enabled`, `queue_tries`, `queue_backoff` config
- [x] `WorkflowRunner::runExistingTrace`, `dispatch`, `dispatchResume`
- [x] `RunWorkflowJob`, `ResumeWorkflowJob` com `failed()` handler
- [x] `POST /workflows/{id}/run` → 202 queued; `POST /traces/{id}/resume` → 202 queued
- [x] Polling via `GET /traces/{id}/json` (`queued`, `running`, `awaiting_node_id`)
- [x] E2E tests + docs (runtime-and-traces, export-and-production, configuration, installation)
- [ ] SSE/broadcast em tempo real para jobs (deferred — polling v1)

### AMA já entregue em `v0.1.2` (baseline para v0.2.0)

- [x] AMA-01 — attachments no workflow stream + `state.attachments` entre iterações
- [x] AMA-02 — `MessageFactory` em `AgentNodeExecutor` e `LlmNodeExecutor`
- [x] AMA-03 — `__studio_thread_id` estável em loops (teste integração)
- [x] AMA-04 — agent com tools + memory via `AgentRunner::runInline`
- [x] AMA-05 — `output_key` alimenta condition/loop (state compartilhado)
- [x] AMA-06 — template `autonomous-lead-qualification` + agent `lead-qualifier`
- [x] AMA-07 — `tool_call` / `tool_result` SSE no harness + canvas `loop_iteration`
- [x] AMA-10 — `AutonomousMultimodalAgentsTest` (loop + agent + attachments + tools)
- [x] AMA-09 — documentação padrão autonomous agent (overview, ai-nodes, attachments, threads, runtime, quickstart)

---

## Lessons Learned

### L-001: Multiple Vite bundles need isolated scope (2026-06-24)

**Context:** Workflow editor loads two production bundles on same page.
**Problem:** Default Vite output leaked shared minified identifiers into global lexical scope → SyntaxError on page load.
**Solution:** `format: 'iife'` per bundle in `vite.config.js`.
**Prevents:** Duplicate identifier errors when adding more studio bundles to same layout.

### L-002: Private disk attachments need authenticated preview route (2026-06-30)

**Context:** Multimodal workflow/agent test harness.
**Problem:** `Storage::url()` apontava para `/storage/...` (403) em disco `local` privado.
**Solution:** `GET /studio/attachments/file?storage_key=` + manter blob preview no composer.

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

---

## Deferred Ideas

- [ ] Autonomia multi-turn dentro de um único nó agent (múltiplas tool rounds sem sair do nó)
- [ ] SSE em tempo real para `RunWorkflowJob` (broadcast vs polling)
- [ ] Remove redundant layout `<link>` tags for bundle-inlined CSS
- [ ] Extract `StudioTestHarness.jsx` shell component if composition grows

---

## Todos

- [x] `workflow-cyclic-graphs` P0 + P1 (T1–T19)
- [x] Docs T20–T21 + `docs/RELEASE.md` v0.2.x section
- [x] AMA-03–07, AMA-10
- [x] `workflow-rag` — KnowledgeBase + executor real + codegen + docs
- [x] AMA-09 — docs dedicated autonomous-agent guide sections
- [ ] Configurar branch protection para `v0.2.x` no GitHub (espelhar `v0.0.x`)
