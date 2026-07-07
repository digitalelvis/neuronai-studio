# Stream Adapters — Tasks

Traceability: cada task referencia os IDs `SA-01..SA-14` da [spec](spec.md).

**Feature:** `stream-adapters` (M4 — Integração externa) · **Linha:** `v0.2.x`
**Princípio:** separação total interno vs externo — endpoints/controllers/SSE do playground e do harness permanecem intocados (SA-08).

## Contexto de código (ancoragem)

| Peça | Onde | Nota |
|------|------|------|
| Adapters neuron | `NeuronAI\Chat\Messages\Stream\Adapters\{VercelAIAdapter,AGUIAdapter}` | `transform(chunk)`, `getHeaders()`, `start()`, `end()` — consomem `TextChunk`/`ToolCallChunk`/`ToolResultChunk`/`ReasoningChunk` |
| Agent stream | `AgentRunner::stream()` → `$agent->stream($msg)->events()` | hoje itera `events()` e filtra `StreamChunk`; falta expor o handler cru p/ `events($adapter)` (SA-07) |
| Workflow nativo | `NeuronAI\Workflow\WorkflowHandler::events($adapter)` | aplica `start()`/`transform()`/`end()` automaticamente |
| Workflow Studio (runtime interpretado) | `WorkflowRunner` / `GraphExecutionLoop` | emite SSE **próprio** (`token {node_id,delta}`, `tool_call`, `tool_result`, `step_*`), **não** chunks Neuron → controller externo precisa converter eventos → chunks p/ alimentar adapter |
| Rotas Studio (intocadas) | `routes/web.php` sob prefix `neuronai-studio` + middleware `web`/gate | integração externa é grupo/arquivo separado |
| Service provider | `NeuronAIStudioServiceProvider::registerRoutes()` | registro condicional do novo grupo |

## Decisão em aberto (resolver na Fase 1 / AD)

- **Ponte workflow interpretado → adapter:** o adapter espera chunks Neuron, mas o runtime interpretado do Studio emite SSE próprio. Opção A: converter cada evento Studio (`token`/`tool_call`/`tool_result`) em `TextChunk`/`ToolCallChunk`/`ToolResultChunk` e chamar `$adapter->transform()`; `start()`/`end()` emitidos manualmente pelo controller. Opção B: emitir linhas do protocolo direto sem adapter (mais frágil). **Recomendado: Opção A** (reusa formatação do adapter, garante paridade com o formato oficial). Registrar como AD ao implementar SA-06.
- **Interrupt (Human node) no stream externo:** mapear `HumanInputRequiredException`/`parallel_interrupt` para um evento de protocolo terminal (ex.: AG-UI `RUN_FINISHED` + custom `state`, Vercel `finish` com metadata) que sinalize "aguardando input" + `trace_id` p/ o client chamar `resume/{protocol}`.

---

## Fase 1 — Registry, config, rotas, controllers, resume, testes

### SA-T1 — Config `stream_adapters` (SA-02)

- **What:** Bloco `stream_adapters` (enabled, route_prefix, middleware, protocols vercel/agui) em `config/neuronai-studio.php` com defaults por env.
- **Where:** `config/neuronai-studio.php`.
- **Done when:** config publicável com defaults `enabled=true`, `route_prefix='api/neuronai'`, `middleware=['api']`, protocols `vercel`/`agui` habilitados.
- **Tests:** coberto indiretamente por SA-T3/SA-T9 (rotas condicionais).
- **Requirements:** SA-02.

### SA-T2 — `StreamAdapterRegistry` (SA-01)

- **What:** Registry que lista protocolos **available** (`vercel`, `agui`) e **roadmap** (`openai-sse`, `anthropic-sse`, `langchain`, `copilotkit`, `websocket`, `inertia`, `ndjson`) com metadados (label, framework-alvo, headers, docs URL, status); factory que resolve protocol → instância de adapter neuron; respeita flags `protocols.*.enabled` da config.
- **Where:** `src/Integration/StreamAdapterRegistry.php` (+ binding singleton no service provider).
- **Done when:** `available()` retorna vercel+agui habilitados; `roadmap()` retorna os demais; `resolve('vercel')` devolve `VercelAIAdapter`, `resolve('agui')` devolve `AGUIAdapter`; protocol desabilitado/desconhecido lança erro claro.
- **Tests:** `StreamAdapterRegistryTest`.
- **Requirements:** SA-01.

### SA-T3 — Registro condicional de rotas + middleware (SA-03, SA-04)

- **What:** Novo arquivo `routes/integration.php` (grupo com `route_prefix` + `middleware` da config + `name('neuronai-studio.integrate.')`); `registerRoutes()` carrega o grupo **apenas** quando `stream_adapters.enabled=true`.
- **Where:** `routes/integration.php`, `src/NeuronAIStudioServiceProvider.php` (`registerRoutes`).
- **Done when:** com `enabled=true` as rotas existem sob o prefix configurado com o middleware configurado; com `enabled=false` nenhuma rota de integração é registrada (`404`/ausente); middleware do grupo é independente do `web`/gate do Studio UI.
- **Tests:** `StreamAdapterRoutesTest` (rotas presentes/ausentes, middleware aplicado).
- **Requirements:** SA-03, SA-04.

### SA-T4 — `AgentRunner::streamHandler()` (SA-07)

- **What:** Método que devolve o handler do agente (`$agent->stream($message)`) **sem** consumir os eventos, para o controller externo chamar `$handler->events($adapter)`. Reusa `makeAgent`/`resolveThread`/`MessageFactory`; não altera `stream()` interno (SA-08).
- **Where:** `src/Runtime/AgentRunner.php`.
- **Done when:** `streamHandler($agent, $payload)` retorna um `AgentHandler` pronto para `events($adapter)`; `stream()` (playground) permanece igual.
- **Tests:** `AgentIntegrateStreamTest` (via controller).
- **Requirements:** SA-07.

### SA-T5 — `AgentIntegrateStreamController` (SA-05)

- **What:** Controller POST que resolve o protocol via registry, instancia o adapter, e faz `response()->stream()` iterando `$handler->events($adapter)` — emitindo as strings do protocolo com os `getHeaders()` do adapter. Aceita `{message, thread_id?, attachments?, context?, parameters?}` (reusa validação de chat/attachments).
- **Where:** `src/Http/Controllers/Integration/AgentIntegrateStreamController.php`.
- **Done when:** `POST {prefix}/agents/{agent}/stream/vercel` produz `text-delta` + header `x-vercel-ai-ui-message-stream: v1`; `.../agui` produz `RUN_STARTED` → `TEXT_MESSAGE_*` → `RUN_FINISHED`; protocol inválido → `404`/erro.
- **Tests:** `AgentIntegrateStreamTest` (vercel + agui + protocol inválido).
- **Requirements:** SA-05, SA-07.

### SA-T6 — Ponte workflow interpretado → adapter (SA-06)

- **What:** Serviço/trait que converte eventos do runtime interpretado do Studio (`token`, `tool_call`, `tool_result`, `step_*`) em chunks Neuron (`TextChunk`, `ToolCallChunk`, `ToolResultChunk`) e os passa por `$adapter->transform()`, com `start()`/`end()` emitidos ao redor. Registrar AD para a decisão A vs B.
- **Where:** `src/Integration/WorkflowStreamBridge.php` (ou concern), reusando `WorkflowRunner` streaming.
- **Done when:** um workflow com nó agent/llm emite deltas de texto no formato do protocolo alvo; tool call/result mapeados; sem alterar `WorkflowStreamController` interno (SA-08).
- **Tests:** `WorkflowIntegrateStreamTest`.
- **Requirements:** SA-06.

### SA-T7 — `WorkflowIntegrateStreamController` (SA-06)

- **What:** Controller POST que roda o workflow via `WorkflowRunner` + `WorkflowStreamBridge`, emitindo o protocolo (vercel/agui). Aceita `{message?, attachments?, context?}`. Ao encontrar interrupt (Human node), encerra o stream sinalizando "awaiting input" + `trace_id`.
- **Where:** `src/Http/Controllers/Integration/WorkflowIntegrateStreamController.php`.
- **Done when:** `POST {prefix}/workflows/{workflow}/stream/{vercel|agui}` streama a execução; workflow com Human node pausa e o stream sinaliza o `trace_id` para resume.
- **Tests:** `WorkflowIntegrateStreamTest` (vercel + agui + pausa em Human node).
- **Requirements:** SA-06.

### SA-T8 — Resume Human node externo (SA-12, SA-13)

- **What:** `WorkflowIntegrateResumeController` POST `traces/{trace}/resume/{protocol}` — reidrata o checkpoint via `WorkflowRunner::resumeInterpreted` + bridge, aceita `{message}`, e continua o stream no protocolo até completar.
- **Where:** `src/Http/Controllers/Integration/WorkflowIntegrateResumeController.php`, `routes/integration.php`.
- **Done when:** workflow pausa em Human node no stream externo; `POST {prefix}/workflows/traces/{trace}/resume/{protocol}` retoma e completa a execução emitindo eventos do protocolo até o fim.
- **Tests:** `WorkflowIntegrateResumeTest` (pausa → resume → completa, vercel + agui).
- **Requirements:** SA-12, SA-13.

### SA-T9 — Testes de formato + regressão zero (SA-08, SA-11)

- **What:** Cobrir formato vercel (`text-delta`, header `x-vercel-ai-ui-message-stream`) e agui (`RUN_STARTED`/`RUN_FINISHED`); assert de que rotas/SSE internos (`agents.chat.stream`, `workflows.trace.stream`, `run.stream`, resume interno) permanecem inalterados.
- **Where:** `tests/Integration/StreamAdapter*Test.php`, regressão em testes de harness existentes.
- **Done when:** suíte cobre ambos protocolos + rotas condicionais/middleware; nenhum teste interno existente quebra.
- **Tests:** `StreamAdapterRoutesTest`, `AgentIntegrateStreamTest`, `WorkflowIntegrateStreamTest`, `WorkflowIntegrateResumeTest`.
- **Requirements:** SA-08, SA-11.

## Fase 2 — Catálogo + Connect Panel

### SA-T10 — Catálogo Studio `/stream-adapters` (SA-09)

- **What:** Página Livewire listando protocolos available vs roadmap (label, protocolo, frameworks-alvo, headers, docs URL) a partir do `StreamAdapterRegistry`; link de nav.
- **Where:** `src/Http/Livewire/StreamAdapters/Index.php` + view, `routes/web.php` (rota interna do Studio), nav.
- **Done when:** catálogo mostra vercel/agui como available e os demais como roadmap; dados vêm do registry.
- **Tests:** `StreamAdaptersCatalogTest` (Livewire render).
- **Requirements:** SA-09.

### SA-T11 — Connect Panel por agente/workflow (SA-10)

- **What:** Painel na edição/preview de agente e workflow com a URL do endpoint (derivada de `route_prefix`) por protocolo habilitado + snippets client (Vercel `useChat`, AG-UI) com botão copiar; para workflow, exibir também URL de **resume**.
- **Where:** componentes Livewire/Blade de agents/workflows + partial `connect-panel`, helper de URL baseado em `route_prefix`.
- **Done when:** painel exibe URLs de stream (e resume p/ workflow) + snippets copiáveis; respeita protocols habilitados.
- **Tests:** `ConnectPanelTest` (URLs corretas por protocol/route_prefix).
- **Requirements:** SA-10.

## Fase 3 — Docs

### SA-T12 — Documentação de integração (SA-01..SA-13)

- **What:** Guias e referência.
- **Where:**
  - `guides/integration/stream-adapters.md` — overview, config, rotas, exemplos vercel + agui
  - `guides/integration/vercel-ai-sdk.md` — `useChat` apontando p/ endpoint do package
  - `guides/integration/ag-ui.md` — client AG-UI + resume Human node
  - `reference/configuration.md` — seção `stream_adapters`
  - `getting-started/installation.md` — habilitar endpoints de integração no host + middleware (`auth:sanctum`)
  - `guides/agents/playground-and-threads.md` — nota: playground usa SSE interno; integração externa é separada
- **Done when:** `.github/scripts/validate-docs.sh` verde; links e exemplos consistentes com rotas/config implementadas.
- **Requirements:** SA-01..SA-13 (documentação).

## Fase 4 — SA-14 (aguarda paridade token streaming)

### SA-T13 — Tokens em nós agent/llm no workflow externo (SA-14)

- **What:** Paridade com `workflow-token-streaming` — deltas de token dentro de nós agent/llm fluindo pela ponte → adapter (não apenas step boundaries).
- **Where:** `WorkflowStreamBridge` (branch token), controller de workflow externo.
- **Done when:** workflow externo emite `text-delta`/`TEXT_MESSAGE_CONTENT` por token dentro de nós agent/llm quando `data.stream=true`.
- **Tests:** `WorkflowIntegrateStreamTest` (branch token).
- **Requirements:** SA-14.
- **Depende de:** `workflow-token-streaming` (✅ done) — pode ser puxada junto da Fase 1 se conveniente, senão fica como P2.

---

## Ordem de execução sugerida

1. **SA-T1 → SA-T2 → SA-T3** (fundação: config + registry + rotas condicionais)
2. **SA-T4 → SA-T5** (agent stream — caminho mais direto, valida vercel/agui end-to-end)
3. **SA-T6 → SA-T7 → SA-T8** (workflow stream + resume — resolve a AD da ponte interpretado→adapter)
4. **SA-T9** (formato + regressão) em paralelo com 2–3
5. **SA-T10 → SA-T11** (catálogo + Connect Panel)
6. **SA-T12** (docs)
7. **SA-T13** (SA-14 — opcional na Fase 1, senão P2)

## Deferred / Notas

- Protocolos roadmap (`openai-sse`, `anthropic-sse`, `langchain`, `copilotkit`, `websocket`, `inertia`, `ndjson`) aparecem **só no catálogo** — não implementar nesta feature.
- Sem codegen/export de controllers — rotas prontas no package substituem (escopo excluído na spec).
- Sem alteração de `fetchSse.js`, SessionAdapters, `StudioChat.jsx` ou dos controllers internos.
