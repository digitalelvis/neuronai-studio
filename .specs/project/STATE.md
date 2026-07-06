# State

**Last Updated:** 2026-07-03
**Development line:** `v0.2.x` (target release `v0.2.1+`)
**Latest published:** `v0.2.0` on `main`
**Current Work:** M4 (`stream-adapters`) **em andamento** — branch `feat/stream-adapters`; Fase 1 entregue (SA-T1..T8 + testes agent/workflow/resume/rotas/registry; 278 verde). Próximo: SA-T10/T11 (catálogo + Connect Panel) → SA-T12 (docs) → SA-T13 (P2). M1/M2/M3 concluídos.

---

## Recent Decisions (Last 60 days)

### AD-008: M4 stream-adapters — separação interno/externo + ponte interpretado→adapter (2026-07-03)

**Decision:** Kickoff do M4 (`stream-adapters`). Endpoints externos (Vercel AI SDK, AG-UI) ficam em grupo/arquivo de rotas **separado** (`routes/integration.php`, prefix `api/neuronai`, middleware próprio configurável) registrado condicionalmente por `stream_adapters.enabled`. Zero alteração no playground/harness interno (controllers, `fetchSse.js`, SessionAdapters, `StudioChat`). Para workflow, como o runtime do Studio é **interpretado** (SSE próprio, não chunks Neuron), a ponte converte eventos (`token`/`tool_call`/`tool_result`) em chunks (`TextChunk`/`ToolCallChunk`/`ToolResultChunk`) e alimenta `$adapter->transform()` (Opção A recomendada; AD final na Fase 1 / SA-T6).
**Reason:** `WorkflowHandler::events($adapter)` só existe no runtime nativo; o Studio roda interpretado. Reusar os adapters oficiais (formato garantido) sem tocar no caminho interno mantém regressão zero e paridade com o protocolo.
**Trade-off:** Ponte adiciona uma camada de conversão de eventos; interrupt (Human node) precisa de mapeamento explícito para evento terminal do protocolo + `trace_id` p/ `resume/{protocol}`.
**Impact:** `StreamAdapterRegistry`, config `stream_adapters`, `routes/integration.php`, `AgentRunner::streamHandler`, `AgentIntegrateStreamController`, `WorkflowStreamBridge`, `WorkflowIntegrateStreamController`, `WorkflowIntegrateResumeController`, catálogo `/stream-adapters`, Connect Panel. Ver [tasks](../features/stream-adapters/tasks.md).

### AD-007: Runtime interpretado para execução paralela (2026-07-03)

**Decision:** Fork/Join usam runtime **interpretado** — `ForkNodeExecutor` roda cada branch sequencialmente em um `BuilderWorkflowState` isolado (clone) até o join, e `JoinNodeExecutor` mescla os resultados por branch id. O codegen nativo emite uma subclasse `ParallelEvent` válida para export, mas a orquestração concorrente via `AsyncExecutor` do Neuron não é exercida em runtime pelo Studio.
**Reason:** Isolamento de estado por branch + resume parcial (reusar o mecanismo de checkpoint/HITL) são mais simples e determinísticos sob o loop interpretado; evita dependência do Amp/AsyncExecutor no caminho do harness.
**Trade-off:** Sem paralelismo real de I/O no runtime interpretado (branches independentes mas sequenciais); aprovação de tool dentro de branch não é dividida por branch (só Human interrupt).
**Impact:** `ParallelBranchRunner`, `ForkNodeExecutor`/`JoinNodeExecutor`, `ParallelBranchInterruptException`, checkpoint `kind: parallel` no `WorkflowRunner`, `GraphValidator::validateParallel`, SSE `branch_started`/`branch_completed`/`parallel_interrupt`.

### AD-006: Checkpoints como decorator opt-in + EloquentPersistence (2026-07-03)

**Decision:** Generalizar checkpoints com um `CheckpointService` + tabela `neuronai_studio_workflow_checkpoints`. Nós caros (agent/llm/rag/tool) optam via `data.checkpoint: true` e são embrulhados por um decorator `CheckpointingExecutor`. Workflows nativos usam `EloquentPersistence` (implementa `SerializablePersistenceInterface`) para persistir `WorkflowInterrupt`.
**Reason:** Evita re-executar chamadas de provider caras no resume sem acoplar a lógica de cache a cada executor; mantém o checkpoint per-trace do Human/ToolApproval intacto.
**Trade-off:** Chave `sha256(trace_id|node_id|iteration|input_hash)` guarda apenas o diff de estado do nó (mesclado no hit); mudanças em chaves voláteis internas são ignoradas no hash para não invalidar indevidamente.
**Impact:** `CheckpointService`, `CheckpointingExecutor`, `WorkflowCheckpoint` model, migration nullable FK + `workflow_key`, config `checkpoints.enabled/ttl`, comando `checkpoints:purge`, `EloquentPersistence`.

### AD-005: Tool approval via NeuronAI `ToolApproval` middleware (2026-07-03)

**Decision:** Reusar o middleware `NeuronAI\Agent\Middleware\ToolApproval` no `DynamicAgent`; converter o `WorkflowInterrupt`/`ApprovalRequest` do agente em `ToolApprovalRequiredException` na camada `AgentRunner`, seguindo o padrão de pausa do Human node.
**Reason:** Evita reimplementar detecção de tool call; mantém pausa/checkpoint consistentes com `pauseForHumanInput` e status `awaiting_input`.
**Trade-off:** Slices 1–2 aprovam **todas** as tools (config vazio). Slice 2 persiste o `WorkflowInterrupt` serializado no checkpoint e restaura para resume real; UI/codegen ficam para slice 3.
**Impact:** `require_tool_approval` no `AgentDefinition` + override no nó agent; novo status `awaiting_tool_approval` no trace (coluna string, sem migration); SSE `tool_approval_required` + `tool_approval_resolved`; resume `approve|reject` via `POST .../resume/stream` (sync) e `.../resume` (async job); handle `rejected` opcional no nó agent. Nota: tools com callback `Closure` quebram a serialização do interrupt — Studio usa tools baseadas em classe.

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
| `workflow-tool-approval` | ✅ done | Slices 1–3 ✅ (backend, resume/API, UI+codegen+docs) |
| `workflow-token-streaming` | ✅ done | Slice 1 (backend token SSE) ✅; slice 2 (toggle canvas + docs polish) ✅ |

### workflow-tool-approval — Slice 1 (backend) entregue

- [x] `ToolApprovalRequiredException` (node_id, pending_tools, message)
- [x] `require_tool_approval` no `AgentDefinition` (migration + cast/fillable) + override no nó agent (`data.require_tool_approval`)
- [x] `AgentRunner` aplica `ToolApproval` middleware quando habilitado; `runInline` converte `WorkflowInterrupt`/`ApprovalRequest` → `ToolApprovalRequiredException`
- [x] `AgentNodeExecutor` anexa `node_id` do grafo à exceção
- [x] `WorkflowRunner::pauseForToolApproval` → status `awaiting_tool_approval` + checkpoint `{ state, node_id, pending_tools, interrupt }` + SSE `tool_approval_required` (interpreted `run`/`resume`)
- [x] 5 testes novos (exceção, runner pausa/regressão, workflow pausa/regressão) — suíte 231 verde

### workflow-tool-approval — Slice 2 (resume + API/SSE) entregue

- [x] `ToolApprovalRequiredException` carrega `serializedInterrupt`; `AgentRunner::toolApprovalException` serializa o `WorkflowInterrupt` para resume real
- [x] `AgentRunner::resumeInlineApproval` — restaura o interrupt via `InMemoryPersistence`, aplica decisão (`approve`/`reject`) nas `Action`s e resume o agente (`chat([], $request)`)
- [x] `AgentNodeExecutor` consome marker `__tool_approval_resume` do state → resume o nó + roteia handle `rejected` opcional
- [x] `WorkflowRunner::resumeInterpreted` aceita `approval`; `resumeToolApproval` reidrata o state do checkpoint, emite SSE `tool_approval_resolved` e re-executa o nó via `GraphExecutionLoop::runFromNode`
- [x] Controllers sync/async aceitam `approval: approve|reject` (message opcional); `dispatchResume`/`ResumeWorkflowJob` propagam `approval`
- [x] 2 testes novos (approve → completa; reject → handle `rejected`) — suíte 233 verde

### workflow-tool-approval — Slice 3 (UI + codegen + docs) entregue

- [x] TA-06: `WorkflowSessionAdapter` guarda `pendingApproval` no SSE `tool_approval_required` + `resumeApproval(decision, feedback)` → `POST .../resume/stream` com `{ approval, message? }`
- [x] TA-06: `StudioChat` trata `tool_approval_required` (card inline) + `handleToolApproval` consome resume stream; loop de packets extraído em `consumeAssistantStream` (reuso send/resume)
- [x] TA-06: `ToolApprovalCard.jsx` — tools pendentes + args + Approve/Reject + feedback opcional (sem modal); `MessageList` renderiza card + badge `Tool approval`
- [x] TA-08: `AgentNodeCodeGenerator` — path agent_id passa `require_tool_approval` (override literal ou `(bool) $agent->require_tool_approval`) ao `runInline`; path inline aplica `$agent->addGlobalMiddleware(new ToolApproval())` + import
- [x] Rebuild `studio-chat.bundle.js` (Vite IIFE)
- [x] Docs: human-in-the-loop (Tool approval vs Human), ai-nodes (approval no agent node), creating-agents (flag + export), runtime-and-traces (status/SSE/resume payload), security-and-access (aprovar tools sensíveis)
- [x] 2 testes codegen novos (`NativeWorkflowExporterTest`) — suíte 235 verde

### workflow-token-streaming — Slice 1 (backend token SSE) entregue

- [x] TS-01: `AgentRunner::streamInline` — generator yield `StreamChunk` + `return AgentRunResult` (conteúdo + tool events) após consumir eventos
- [x] TS-03/TS-06: `AgentNodeExecutor` streaming branch (`data.stream`) → emite SSE `token` `{node_id, delta}` entre `step_started`/`step_completed`; fallback blocking para structured e tool-approval (sem regressão)
- [x] TS-02: `LlmNodeExecutor` streaming via `AIProviderInterface::stream()` + `getReturn()` → `output_key`
- [x] TS-04/TS-05: sem mudança — `WorkflowStreamController` propaga `token` e `StudioChat`/`WorkflowSessionAdapter` já agregam `token` na bolha assistant
- [x] TS-08: `WorkflowTokenStreamingTest` (5 testes: agent stream, llm stream, 2 regressões blocking, tool-approval fallback) — suíte 240 verde
- [x] Docs: runtime-and-traces (evento `token` + seção Token streaming), ai-nodes (opção `stream` + seção Streaming)

### workflow-token-streaming — Slice 2 (toggle canvas + docs polish) entregue

- [x] TS-07: `StreamToggleField` compartilhado no inspector (agent/llm) — desabilita + nota quando `structured` (paridade com fallback backend)
- [x] Default on no harness: `stream: true` no default config de novos nós agent/llm (`WorkflowCanvas.addNodeAt`)
- [x] Rebuild `resources/js/dist/workflow-canvas.bundle.js`
- [x] Docs: `frontend-bundles.md` (token handling StudioChat/WorkflowSessionAdapter), `playground-and-threads.md` (parity harness ↔ playground)

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
| `workflow-checkpoints-persistence` | ✅ done | CP-01..08 ✅ — service + decorator + EloquentPersistence + purge |
| `workflow-parallel-execution` | ✅ done | PE-01..09 ✅ — fork/join runtime, branch resume, codegen, canvas (PE-08 preview parcial) |

### workflow-checkpoints-persistence — entregue (CP-01..08)

- [x] CP-05: migration `neuronai_studio_workflow_checkpoints` (FK nullable + `workflow_key`, `input_hash`, `state_payload`, `expires_at`, unique node/iteration) + `WorkflowCheckpoint` model + config `checkpoints.enabled/ttl`
- [x] CP-01/CP-06: `CheckpointService` — chave `sha256(trace_id|node_id|iteration|input_hash)`, hash do input (exclui chaves voláteis) → invalidação, lookup/store/forget, TTL + `purgeExpired`
- [x] CP-02/CP-03: `CheckpointingExecutor` (decorator) embrulha agent/llm/rag/tool com `data.checkpoint: true`; hit mescla o diff de estado e pula o executor interno; escopo por iteração de loop
- [x] CP-04: `EloquentPersistence` (`PersistenceInterface` + `SerializablePersistenceInterface`) persiste `WorkflowInterrupt` de workflows nativos via `workflow_key` + node `__native_interrupt`
- [x] CP-05: `PurgeCheckpointsCommand` (`neuronai-studio:checkpoints:purge`)
- [x] CP-08: `CheckpointServiceTest` + `EloquentPersistenceTest` + fixture `SampleInterruptNode` + `MigrationTest` (10 testes) — suíte 250 verde

### workflow-parallel-execution — entregue (PE-01..09)

- [x] PE-01/PE-04: node types `fork`/`join` (config + executors + registry); `GraphValidator::validateParallel` (fork→join default, ≥1 branch, join pareado)
- [x] PE-02/PE-03: `ForkNodeExecutor` roda branches via `ParallelBranchRunner` em estado isolado até o join; `GraphExecutionLoop::runFromNode` com `stopAtNodeId`; `JoinNodeExecutor` mescla `{ branchId: result }` em `output_key`
- [x] PE-05/PE-06: `ParallelBranchInterruptException` + checkpoint `kind: parallel`; `WorkflowRunner::pauseForParallelInterrupt`/`resumeParallel` retoma só a branch pendente, re-executa branches não iniciadas e reusa concluídas; SSE `parallel_interrupt`
- [x] PE-07: `GraphTranspiler` + `Fork/JoinNodeCodeGenerator` + stub `native-parallel-event` emitem subclasse `ParallelEvent`; fork retorna `new XParallelEvent([...])`, branches retornam `StopEvent(result:)`, join lê `getAllResults()`
- [x] PE-01/PE-08: canvas fork (handle por branch) + inspector branch editor / join `output_key`; rebuild `workflow-canvas.bundle.js`
- [x] PE-09: `WorkflowParallelExecutionTest` (merge, human interrupt + resume parcial, validator) + `NativeWorkflowExporterTest` (ParallelEvent compila) — 4 testes, suíte 254 verde
- [ ] PE-08 parcial: preview de resultados agregados no inspector do join (deferred); tool approval dentro de branch não dividido por branch

### M3 template pack + slug fix — entregue (2026-07-03)

- [x] Templates de referência (providers reais, sem fake): `parallel-support-triage` (intermediate — fork → 3 branches LLM sentiment/facts/priority → join → agente compositor, todos com `checkpoint: true`) e `parallel-triage-hitl` (advanced — mesma base + branch `human` que pausa via `parallel_interrupt`, resume reusa checkpoints das branches concluídas)
- [x] Agente `support-triage-composer` (sintetiza análises paralelas + nota do revisor em triage summary + resposta sugerida)
- [x] Fix `Editor::resolveSlug` — auto-save do canvas (`saveGraphBeforeRun` → `save()`) não regrava o slug quando o nome não mudou; quando muda, gera slug único excluindo o próprio id (evita `UniqueConstraintViolationException` em `workflow_definitions.slug`)
- [x] Docs `guides/templates.md` (tabelas + seção "Parallel Support Triage" com input de exemplo e resultado esperado)
- [x] Testes: `TemplateRegistryTest` (18 templates), `TemplateInstallerTest` (2 novos — install/valida fork/join + HITL), `WorkflowEditorSaveTest` (2 novos — slug estável / dedupe) — suíte 258 verde
- [ ] Não commitados no fix: `resources/**/*.css` (artefatos de build minificado, fora de escopo)

## M4 progress snapshot

| Feature | Status | Notas |
|---------|--------|-------|
| `stream-adapters` | ✅ done | branch `feat/stream-adapters`; Fase 1–3 entregues (SA-T1..T13); suíte 279 verde |

### stream-adapters — plano (SA-T1..SA-T13)

- [x] SA-T1 — config `stream_adapters` (enabled, route_prefix, middleware, protocols) (SA-02)
- [x] SA-T2 — `StreamAdapterRegistry` (available vercel/agui + roadmap; resolve → adapter neuron) (SA-01)
- [x] SA-T3 — `routes/integration.php` + registro condicional no service provider + middleware próprio (SA-03, SA-04)
- [x] SA-T4 — `AgentRunner::streamHandler()` expõe handler p/ `events($adapter)` (SA-07)
- [x] SA-T5 — `AgentIntegrateStreamController` (POST agent stream vercel/agui) (SA-05)
- [x] SA-T6 — `WorkflowStreamBridge` (evento interpretado → chunk Neuron → `transform`; Opção A; fallback step-boundary + sinal `awaiting_input`+`trace_id`) (SA-06)
- [x] SA-T7 — `WorkflowIntegrateStreamController` (POST workflow stream vercel/agui; pausa Human node sinaliza `trace_id`) (SA-06)
- [x] SA-T8 — `WorkflowIntegrateResumeController` (`resume/{protocol}` Human node → completa stream) (SA-12, SA-13)
- [x] SA-T9 — testes formato vercel/agui + regressão zero (registry/rotas/agent/workflow/resume ✅; suíte 279 verde) (SA-08, SA-11)
- [x] SA-T10 — catálogo Studio `/stream-adapters` (SA-09)
- [x] SA-T11 — Connect Panel URLs stream+resume + snippets `useChat`/AG-UI (SA-10)
- [x] SA-T12 — docs integration (stream-adapters, vercel-ai-sdk, ag-ui)
- [x] SA-T13 — SA-14 tokens em nós agent/llm no workflow externo via `WorkflowStreamBridge` (SA-14)

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

### L-003: Resume de fork deve reprocessar branches não iniciadas (2026-07-03)

**Context:** Interrupt (Human node) dentro de uma branch paralela.
**Problem:** Retomar apenas a branch pendente perdia as branches que ainda não tinham iniciado (as posteriores ao interrupt na ordem sequencial).
**Solution:** No resume, o `ForkNodeExecutor` itera todas as branches: pula as concluídas (do checkpoint), retoma a pendente com o input injetado, e roda as não iniciadas do zero.
**Prevents:** Perda silenciosa de resultados de branch em workflows com >1 branch e HITL.

### L-004: Slug do workflow não pode ser recalculado em todo save (2026-07-03)

**Context:** Auto-save do canvas antes de rodar teste (`saveGraphBeforeRun` → `Editor::save()`), com dois workflows de mesmo nome (ex.: dois installs do mesmo template).
**Problem:** `save()` fazia `slug = Str::slug($this->name)` sempre, sobrescrevendo o sufixo de dedupe (`-1`) → `UNIQUE constraint failed: workflow_definitions.slug`.
**Solution:** `Editor::resolveSlug` mantém o slug atual quando o nome não muda; quando muda, gera slug único ignorando o próprio id.
**Prevents:** Colisão de slug ao testar/salvar workflows com nomes duplicados (comum com templates reinstalados).

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
- [ ] **M4 `stream-adapters`** — SA-T10..SA-T13 (branch `feat/stream-adapters`; SA-T1..T8 ✅, SA-T9 parcial, suíte 278 verde)
