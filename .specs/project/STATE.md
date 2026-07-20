# State

**Last Updated:** 2026-07-20
**Development line (features):** `v0.8.x` (pós-M7; próximo milestone TBD)
**Patch line:** `v0.8.x`
**Latest published:** `v0.8.0` on Packagist / `main`
**Current Work:** M7 ✅ publicado em `v0.8.0`. Próximo: definir M8 / débitos (OBS-06, LangSmith/OTel, invoke).

---

## Recent Decisions (Last 60 days)

### AD-020: M7 Observabilidade externa + linha `v0.8.x` (2026-07-17)

**Decision:** Após M6 Execute completo em `v0.7.x`, abrir milstone **M7 — Observabilidade externa** com feature `external-observability`. Modelo de produto alinhado ao Langflow: **native tracing** (Debugger/TelemetryTracker já existe) + **monitoring** Inspector e Langfuse **env-first**. Develop/PRs de M7 → `v0.8.x` após release `v0.7.0`; patch line = `v0.7.x`.
**Scope M7 MVP (OBS-01…04 + docs OBS-05):** (1) toggle `NEURONAI_STUDIO_NATIVE_TRACING`; (2) wire `Inspector\Neuron\InspectorObserver` explícito (corrige gap EventBus quando TelemetryTracker já observou); (3) Langfuse via adapter com `$branchId` + keys `LANGFUSE_*`; (4) `ObservabilityManager` como único attach point. P3 Settings status e LangSmith fora do MVP.
**Reason:** Mercado (Langflow) separa traces locais de exportadores; Studio já tem native/usage (M4/M5) mas `inspector_enabled` é config morta e Langfuse/LangSmith não existem. Env-first evita UI de secrets. LangSmith sem SDK PHP → deferred OTel.
**Trade-off:** Não inclui nó `invoke`, Settings write, nem catálogo multi-vendor. `axyr/laravel-langfuse` precisa adapter até upstream aceitar `branchId`.
**Impact:** Spec/context em [.specs/features/external-observability/](../features/external-observability/). ROADMAP M7. Deferred: invoke node, LangSmith, bridge URL TraceDetail, Settings P3.

### AD-019: M6 Runtime/Agent + linha `v0.7.x` (2026-07-16)

**Decision:** Após publicar `v0.6.0` (UE / M5 completo), abrir `v0.7.x` como linha de feature para M6 — desempenho e flexibilidade de runtime/agent. Patch line = `v0.6.x`. Encerrar `v0.5.x` / `v0.6.x` para features novas.
**Scope M6 (ordem Execute):** (1) `agent-tool-controls` — `tool_max_runs` / `parallel_tool_calls` + live tool SSE; (2) `async-run-progress` — ProgressEmitter buffer + SSE tail (sem Echo); (3) `interpreted-parallel-concurrency` — Amp concurrent fork/join no path interpretado.
**Reason:** Neuron já faz multi-round tools; gaps do Studio são knobs, observabilidade mid-loop, progresso em jobs e concorrência real (AD-007 sequencial). Billing/Usage avançado permanece deferred.
**Impact:** ROADMAP/STATE/RELEASE + specs M6 → `v0.7.x`. Decisões abertas SSE-async e multi-turn-in-node resolvidas (ver ROADMAP). Context: [.specs/features/m6-runtime-agent/context.md](../features/m6-runtime-agent/context.md).

### AD-018: Linha `v0.5.x` (patch) + `v0.6.x` (UE / fechar M5) (2026-07-16)

**Decision:** Após publicar `v0.5.0` (UA), manter `v0.5.x` como patch line e abrir `v0.6.x` como linha de feature para Execute de `usage-export-api`. Encerrar `v0.4.x` para features.
**Reason:** UA saiu em minor própria; UE completa o critério M5 (API HTTP host) e merece série dedicada. Ruleset `v*.*.x` já cobre ambas as linhas.
**Impact:** ROADMAP/STATE/RELEASE + tasks M5 apontam UE → `v0.6.x`. PRs de UE → `v0.6.x`; patches `0.5.*` → `v0.5.x`.

### AD-017: Linha ativa `v0.4.x` pós-release 0.4.0 (2026-07-16)

**Decision:** Encerrar `v0.3.x` como linha ativa de feature; desenvolver a partir de `v0.4.x`. CE já publicado em `v0.4.0`. Débito UE/UA e novas features miram PRs → `v0.4.x`.
**Reason:** Release `v0.4.0` consolidou M4 leftovers + CE na `main`/Packagist.
**Impact:** Superseded by AD-018 após `v0.5.0`.

### AD-016: UA inclui Test Pretty + UsageQuery mínimo (2026-07-16)

**Decision:** Ampliar `usage-analytics` para chips de tokens/custo no Test Pretty (`WorkflowThread` / Completed header) além de Debugger + Dashboard. Dashboard usa `UsageQuery::aggregate` extraído no UA (partial UE-T2); HTTP export permanece débito. Tasks UA-T1…T11.
**Reason:** Pretty é a superfície principal do harness; operadores veem latência mas não spend. Bloquear Dashboard em UE HTTP inteiro atrasa valor Studio.
**Impact:** Spec/design/tasks UA + ROADMAP/M5 index atualizados; UA shipped em `v0.5.0`.

### AD-015: UE + UA como débito M5 — não Execute agora (2026-07-15)

**Decision:** Manter `usage-export-api` e `usage-analytics` no roadmap com specs/design/tasks intactos, mas **não executar** até retorno explícito. CE permanece a fatia M5 entregue nesta onda.
**Reason:** Metering no DB (provider/model/cost + nest rollup) já desbloqueia o host via queries diretas; API de export e superfície Studio podem esperar sem invalidar o design.
**Impact:** UA retomado e shipped (`v0.5.0`); UE retomado sob AD-018 na linha `v0.6.x`.

### AD-012: RELEASE_TOKEN para push do release em main (2026-07-15)

**Decision:** Autenticar `.github/workflows/release.yml` com secret `RELEASE_TOKEN` (fine-grained PAT de um Administrator), nunca com `GITHUB_TOKEN`. Push do commit em `main` **antes** da tag. Falhar cedo se o secret faltar.
**Reason:** Repos user-owned não permitem bypass do app GitHub Actions no ruleset; `GITHUB_TOKEN` gera tags órfãs no Packagist quando o push de `main` é rejeitado (GH013).
**Impact:** Setup one-time em [docs/RELEASE.md](../../docs/RELEASE.md); ruleset Mantém bypass só para RepositoryRole Administrator.

### AD-014: M5 design — custo denormalizado + parent rollup (2026-07-15)

**Decision:** Persistir `provider`/`model`/`estimated_cost` no span LLM e `estimated_cost` (+ opcional `parent_run_id`) no run. Pricing em `neuronai-studio.usage.pricing`. Nested agent/LLM sob workflow incrementa o parent run; agregados de janela **excluem** children para não duplicar. Fechar gaps de meter em `stream`/`streamHandler` e `LlmNodeExecutor`. Export: `GET usage` + `GET usage/runs/{run}` sob prefixo/middleware de integração, independente de `stream_adapters.enabled`. Dashboard: janela fixa 30 dias via `UsageQuery`.
**Reason:** `InferenceStop` não carrega model; workflow parent hoje fica com 0 tokens porque LLM vive em runs filhos; LlmNodeExecutor chat/stream bypassa tracker.
**Impact:** Ver designs CE/UE/UA. Finalize de run = own spans + children aggregates.

### AD-013: M5 host-first + Dashboard mínimo (2026-07-15)

**Decision:** M5 prioriza `cost-estimation` + `usage-export-api` para o host meter/faturar. `usage-analytics` fica **mínimo**: evoluir o Dashboard Livewire existente + badges de tokens no Debugger — sem página dedicada Usage/BI neste milstone. Context compartilhado em `.specs/features/m5-analytics-billing/context.md`.
**Reason:** Token persistence já existe (M4); o gap de produto é API de metering para o host app. Studio só precisa de um sinal operacional leve.
**Impact:** Ordem de design/implementação: CE → UE → UA. Página Usage avançada, multi-tenant attribution, embeddings cost e billing providers ficam em Deferred Ideas.

### AD-010: Linha de desenvolvimento v0.3.x + M5 (2026-07-15)

**Decision:** Encerrar `v0.2.x` como linha ativa; abrir `v0.3.x` a partir de `main` alinhada a Packagist `v0.3.1`. Planejar M5 (Analítica e Faturamento) em cima de tokens já persistidos em `StudioTraceSpan` / `TelemetryTracker`.
**Reason:** M1–M4 já saíram em `v0.3.0`; `v0.3.1` corrigiu metadados de release. Nova minor series evita misturar patches de governança com features de usage/billing.
**Impact:** PRs de feature → `v0.3.x`; release PR `v0.3.x` → `main` quando M5 estiver estável. Specs M5: ver AD-013.

### AD-011: Absorver tag órfã v0.3.1 na main (2026-07-15)

**Decision:** Merge do commit `chore(release): 0.3.1` (tag Packagist) de volta em `main` via hotfix, com `[skip ci]` no merge commit, em vez de retag destrutivo.
**Reason:** O push do release-it divergiu do tip de `main` (PR #22); Packagist apontava para um SHA fora da ancestry, e `package.json`/`CHANGELOG` na tip ficaram em `0.3.0`.
**Impact:** `git describe` em `main` volta a reportar `v0.3.1`; próximo release real partirá dessa base.


### AD-009: Unified Threads, Runs, and Traces (2026-07-07)

**Decision:** Refatorar a execução de Workflows e Agents para unificar sob a nomenclatura/conceito de StudioRuns e StudioThreads.
**Reason:** Unificação semântica (runs vs traces), suporte a pausas distribuídas para Agents (HITL/Tool Approval) e rastreamento de tokens por TraceSpans.
**Impact:** `StudioThread`, `StudioRun`, `StudioTrace`, `StudioTraceSpan` substituem os legados `WorkflowTrace`, `WorkflowTraceStep`, `WorkflowCheckpoint`.

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

**Decision:** Priorizar M1 with three features P0 (`workflow-cyclic-graphs`, `autonomous-multimodal-agents`, `workflow-rag`) antes de P1/P2.
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

- Nenhum blocker ativo.

---

## M1 progress snapshot

| Feature | Status | Notas |
|---------|--------|-------|
| `workflow-cyclic-graphs` | ✅ done | P0 + P1 entregues |
| `autonomous-multimodal-agents` | ✅ done | AMA-09 docs entregue |
| `workflow-rag` | ✅ done | Fatia 1–3 (backend, UI, codegen, docs) |

---

## M2 progress snapshot

| Feature | Status | Notas |
|---------|--------|-------|
| `workflow-structured-output` | ✅ done | T1–T17 ✅; T12 parcial — hint dot notation só no condition (loop sem inspector) |
| `workflow-tool-approval` | ✅ done | Slices 1–3 ✅ (backend, resume/API, UI+codegen+docs) |
| `workflow-token-streaming` | ✅ done | Slice 1 (backend token SSE) ✅; slice 2 (toggle canvas + docs polish) ✅ |

---

## M3 progress snapshot

| Feature | Status | Notas |
|---------|--------|-------|
| `workflow-queue-runner` | ✅ done | T1–T11 ✅ — `RunWorkflowJob`, `ResumeWorkflowJob`, async run/resume API, polling, docs |
| `workflow-checkpoints-persistence` | ✅ done | CP-01..08 ✅ — service + decorator + EloquentPersistence + purge |
| `workflow-parallel-execution` | ✅ done | PE-01..09 ✅ — fork/join runtime, branch resume, codegen, canvas (PE-08 preview parcial) |

---

## M4 progress snapshot

| Feature | Status | Notas |
|---------|--------|-------|
| `stream-adapters` | ✅ done | branch `feat/stream-adapters`; Fase 1–3 entregues (SA-T1..T13); suíte 279 verde |
| `unified-runs-and-traces` | ✅ done | T1–T7 concluídos; migrations, models, adapters, token tracking, 279 testes verde |

---

## M5 progress snapshot

| Feature | Status | Notas |
|---------|--------|-------|
| `cost-estimation` | ✅ done | CE-T1…T13 — shipped `v0.4.0` |
| `usage-analytics` | ✅ done | UA-T1…T11 — shipped `v0.5.0` |
| `usage-export-api` | ✅ done | UE-T1…T7 — shipped `v0.6.0` |

---

## M6 progress snapshot

| Feature | Status | Notas |
|---------|--------|-------|
| `agent-tool-controls` | ✅ done | knobs + live tool SSE on `v0.7.x` |
| `async-run-progress` | ✅ done | ProgressEmitter + SSE tail |
| `interpreted-parallel-concurrency` | ✅ done | Amp concurrent fork/join + sequential fallback |

**M6 código ✅ — publicado em `v0.7.0`.**

---

## M7 progress snapshot

| Feature | Status | Notas |
|---------|--------|-------|
| `external-observability` | ✅ done | OBS-01…05; OBS-06 P3 deferred; branch `feat/external-observability` |

**M7 código ✅ — publicado em `v0.8.0`.**

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

### L-004: EventBus não auto-anexa Inspector se já houve observe() (2026-07-17)

**Context:** Planejamento M7 / Inspector APM.
**Problem:** `TelemetryTracker` chama `$agent->observe(...)` e inicializa o scope no `EventBus`; com isso o Neuron **não** registra o `InspectorObserver` default mesmo com `INSPECTOR_INGESTION_KEY`.
**Solution:** Attach explícito de `Inspector\Neuron\InspectorObserver::instance()` via `ObservabilityManager` quando enabled + key.
**Prevents:** “Key setada mas nada no Inspector.dev” em runs do Studio.

### L-005: Slug do workflow não pode ser recalculado em todo save (2026-07-03)

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
| stream-adapters | 2026-07-03 | 0.2.x | ✅ Done |
| unified-runs-and-traces | 2026-07-07 | 0.2.x | ✅ Done |
| cost-estimation | 2026-07-16 | 0.4.0 | ✅ Done |
| usage-analytics | 2026-07-16 | 0.5.0 | ✅ Done |
| usage-export-api | 2026-07-16 | 0.6.0 | ✅ Done |
| agent-tool-controls | 2026-07-17 | 0.7.x | ✅ Done |
| async-run-progress | 2026-07-17 | 0.7.x | ✅ Done |
| interpreted-parallel-concurrency | 2026-07-17 | 0.7.x | ✅ Done |
| external-observability | 2026-07-17 | 0.8.x | ✅ Done |

---

## Deferred Ideas

- [x] **M5:** `usage-export-api` (UE-T1…T7) — shipped `v0.6.0`
- [x] **M5:** `usage-analytics` (UA-T1…T11) — shipped `v0.5.0`
- [x] Autonomia multi-turn / tool rounds no nó agent — Neuron já faz; M6 expõe knobs + live SSE (`agent-tool-controls`)
- [x] SSE em tempo real para `RunWorkflowJob` — M6 `async-run-progress` (buffer + SSE tail; Echo deferred)
- [x] **M7 Specify:** monitoring externo Inspector + Langfuse (env-first) — Execute em `v0.8.x`
- [x] **M7 Execute:** OBS-01…05 (`ObservabilityManager`, Inspector wiring, Langfuse adapter, docs)
- [ ] Laravel Echo / `ShouldBroadcast` como transporte de progresso async
- [ ] Tool approval dentro de parallel branches
- [ ] Remove redundant layout `<link>` tags for bundle-inlined CSS
- [ ] Extract `StudioTestHarness.jsx` shell component if composition grows
- [ ] Página dedicada Usage / charts / filtros avançados (além do Dashboard mínimo M5)
- [ ] Multi-tenant / user attribution em usage
- [ ] Custo de embeddings / RAG como linha separada
- [ ] Integração com billing providers (Stripe, etc.)
- [ ] SO T12 loop hint; PE-08 join preview; RAG hybrid/MMR
- [ ] **Nó `invoke` / hook allowlisted** no canvas (customização simples; fora M7 observability)
- [ ] LangSmith via OpenTelemetry (sem SDK PHP)
- [ ] Bridge Studio TraceDetail ↔ URL Inspector/Langfuse
- [ ] OBS-06 Settings status page read-only (P3 M7)

---

## Todos

- [x] `workflow-cyclic-graphs` P0 + P1 (T1–T19)
- [x] Docs T20–T21 + `docs/RELEASE.md` v0.2.x section
- [x] AMA-03–07, AMA-10
- [x] `workflow-rag` — KnowledgeBase + executor real + codegen + docs
- [x] AMA-09 — docs dedicated autonomous-agent guide sections
- [x] Rulesets / required status checks alinhados ao CI consolidado
- [x] **M4 `stream-adapters`** — SA-T10..SA-T13 (branch `feat/stream-adapters`; SA-T1..T8 ✅, SA-T9 parcial, suíte 278 verde)
- [x] **Unified Runs and Traces** — T1-T7 concluídos (unificação de tabelas, token tracking, api unificada, 279 testes verde)
- [x] Publicar ciclo M1–M4 (`v0.3.0` / `v0.3.1`) e absorver tag órfã em `main`
- [x] Abrir linha `v0.3.x` e atualizar ROADMAP/STATE/RELEASE
- [x] Absorver tag órfã `v0.3.2` em `main`
- [x] Release workflow: `RELEASE_TOKEN` + push `main` antes da tag (AD-012)
- [x] Secret `RELEASE_TOKEN` configurado; `v0.3.3` publicado com commit na ancestry de `main`
- [x] Especificar M5 (Discuss → Spec) — AD-013; specs CE / UE / UA
- [x] Design M5 — AD-014; design.md CE / UE / UA
- [x] Tasks M5 — index + CE/UE/UA tasks.md (28)
- [x] Execute M5 `cost-estimation` (CE-T1…T13)
- [x] Execute M5 `usage-analytics` (UA-T1…T11, Pretty) — `v0.5.0`
- [x] Sync ROADMAP/STATE/RELEASE pós-`v0.5.0` + abrir `v0.6.x` (AD-018)
- [x] Ruleset development lines `v*.*.x` (`apply-branch-rules.sh`)
- [x] Execute M5 `usage-export-api` (UE-T1…T7) em `v0.6.x`
- [x] Sync ROADMAP/STATE/RELEASE pós-`v0.6.0` + abrir `v0.7.x` (AD-019)
- [x] Especificar / design / tasks M6 (ATC + ARP + IPC)
- [x] Execute M6 `agent-tool-controls` → `async-run-progress` → `interpreted-parallel-concurrency` em `v0.7.x`
- [x] Specify M7 `external-observability` + AD-020 + ROADMAP/STATE (2026-07-17)
- [x] Design + tasks M7 `external-observability`
- [x] Execute M7 OBS-01…05 (`feat/external-observability`)
- [x] Release `v0.7.0` (M6) + abrir branch/linha `v0.8.x`
- [x] Merge M7 → `v0.8.x` → release `v0.8.0`
- [ ] Definir próximo milestone (M8) / débitos OBS-06 · LangSmith · invoke
