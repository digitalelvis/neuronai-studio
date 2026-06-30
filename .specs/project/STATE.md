# State

**Last Updated:** 2026-06-30
**Development line:** `v0.2.x` (target release `v0.2.0`)
**Latest published:** `v0.1.2` on `main`
**Current Work:** M1 — `workflow-cyclic-graphs` (feature 1/3)

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

_None._

---

## M1 progress snapshot

| Feature | Status | Notas |
|---------|--------|-------|
| `workflow-cyclic-graphs` | 🔄 in progress | P0: loop runtime, validation, canvas, template, tests — P1: codegen + harness inspector |
| `autonomous-multimodal-agents` | 🟡 partial | Upload, `MessageFactory`, validação attachments, preview route — ver AMA abaixo |
| `workflow-rag` | ⏳ planned | Depende de ciclos + executor real |

### AMA já entregue em `v0.1.2` (baseline para v0.2.0)

- [x] AMA-02 — `MessageFactory` em `AgentNodeExecutor` e `LlmNodeExecutor`
- [x] AMA-01 parcial — attachments no workflow stream + resume + `state.attachments`
- [x] Upload/preview (`AttachmentController` store + show route)
- [x] Codegen agent/llm com `MessageFactory`
- [ ] AMA-03 — thread estável em loops (aguarda `workflow-cyclic-graphs`)
- [ ] AMA-04–07, AMA-10 — template autonomous-lead-qualification, tool events no harness

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
| multimodal-attachments (partial AMA) | 2026-06-30 | 0.1.2 | ✅ Done |

---

## Deferred Ideas

- [ ] Autonomia multi-turn dentro de um único nó agent (múltiplas tool rounds sem sair do nó)
- [ ] SSE em tempo real para `RunWorkflowJob` (broadcast vs polling)
- [ ] Remove redundant layout `<link>` tags for bundle-inlined CSS
- [ ] Extract `StudioTestHarness.jsx` shell component if composition grows

---

## Todos

- [x] `workflow-cyclic-graphs`: gerar `tasks.md` e implementar P0 (branch `feat/workflow-cyclic-graphs`)
- [ ] `workflow-cyclic-graphs`: P1 — `LoopNodeCodeGenerator`, inspector iteração no harness
- [ ] Atualizar `docs/RELEASE.md` consumidores: linha ativa `v0.2.x`
- [ ] Configurar branch protection para `v0.2.x` no GitHub (espelhar `v0.0.x`)
