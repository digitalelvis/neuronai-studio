# Queue Runner para Workflows — Tasks

**Design**: `.specs/features/workflow-queue-runner/design.md`
**Spec**: `.specs/features/workflow-queue-runner/spec.md`
**Status**: Approved — M3 feature 9

> **TESTING.md**: inexistente neste repositório. Matriz inferida dos padrões em `tests/`:
>
> | Camada | Tipo de teste | Comando gate | Parallel-Safe |
> |--------|---------------|--------------|---------------|
> | `src/Jobs/*` | unit | `vendor/bin/phpunit --filter {Class}Test` | Sim |
> | `WorkflowRunner` / controllers | integration | `vendor/bin/phpunit --filter {Class}Test` | Não (DB) |
> | Canvas JS | none | — | N/A (fora de escopo v1) |
> | `docs/` | none | revisão manual | Sim |

---

## Parallel work boundaries (M2 agent)

Este milestone foi escolhido para rodar **em paralelo com M2** (`workflow-structured-output` fases 4–6). Respeitar:

| Evitar tocar (M2) | Foco M3 neste branch |
|-------------------|----------------------|
| `resources/js/studio-canvas/**` | `src/Jobs/**`, controllers novos |
| `StructuredOutputFields.jsx`, `NodeConfigForm.jsx` | `WorkflowRunController` |
| `LlmNodeCodeGenerator`, `AgentNodeCodeGenerator` | `RunWorkflowJob`, `ResumeWorkflowJob` |
| `Editor.php` (output classes canvas) | Migration opcional + docs |

**`WorkflowRunner.php`**: adicionar métodos novos (`dispatch`, `runExistingTrace`); **não refatorar** `runInterpreted` / `resume*` além do mínimo para aceitar trace pré-criado.

---

## Execution Plan

### Phase 1: Runner contract (Sequential)

Trace upfront exige separar criação de trace da execução.

```
T1 → T2
```

### Phase 2: Jobs (Sequential)

```
T2 ──→ T3 → T4
```

### Phase 3: API + routes (Parallel OK após T3)

```
T3 ──┬→ T5 [P]
     └→ T6 [P]
T5 + T6 ──→ T7
```

### Phase 4: Resume async (P1 — Sequential após T4)

```
T4 ──→ T8 → T9
```

### Phase 5: Integration + docs (Sequential)

```
T3 + T7 ──→ T10 → T11
```

---

## Task Breakdown

### T1: Config `async_runs_enabled` e retries

**What**: Flag global para habilitar dispatch assíncrono; retries do job configuráveis.
**Where**: `config/neuronai-studio.php`
**Depends on**: None
**Reuses**: Chaves existentes `queue`, `queue_connection`
**Requirement**: QR-01, QR-04 (base)

**Done when**:

- [ ] Chave `async_runs_enabled` (default `false`) — harness SSE permanece default
- [ ] Chave `queue_tries` (default `1`) e `queue_backoff` (default `30`) para jobs de workflow
- [ ] Gate: `php -r "require 'vendor/autoload.php'; var_export(config('neuronai-studio.async_runs_enabled'));"`

**Tests**: none
**Gate**: quick

**Commit**: `feat(workflows): add async run and queue retry config`

---

### T2: `WorkflowRunner::runExistingTrace`

**What**: Executar workflow contra trace já persistido (`queued` → `running` → terminal), sem criar trace duplicado.
**Where**: `src/Runtime/WorkflowRunner.php`
**Depends on**: None
**Reuses**: `runInterpreted`, `runNative`, `finalizeTrace`, `pauseForHumanInput`
**Requirement**: QR-03

**Done when**:

- [ ] Método público `runExistingTrace(WorkflowTrace $trace, WorkflowDefinition $workflow, array $input = [], ?callable $emitter = null): WorkflowTrace`
- [ ] Trace com status `queued` ou `running` aceito; atualiza `started_at` ao iniciar execução
- [ ] `run()` existente inalterado em comportamento externo (continua criando trace com `running`)
- [ ] Exceções não capturadas propagam; trace permanece `failed` via caller (job)
- [ ] Gate: `vendor/bin/phpunit --filter WorkflowRunnerTest`

**Tests**: unit/integration (estender `tests/WorkflowRunnerTest.php` com `test_run_existing_trace_completes`)
**Gate**: quick

**Commit**: `feat(workflows): run workflow against pre-created trace`

---

### T3: `RunWorkflowJob`

**What**: Job `ShouldQueue` que executa `runExistingTrace` com config de queue do pacote.
**Where**: `src/Jobs/RunWorkflowJob.php`
**Depends on**: T1, T2
**Reuses**: Padrão Laravel `ShouldQueue`, `InteractsWithQueue`, `SerializesModels`
**Requirement**: QR-01, QR-03, QR-04

**Done when**:

- [ ] Constructor recebe `traceId`, `workflowId`, `input`; aplica `onQueue(config('neuronai-studio.queue'))` e `onConnection` quando setado
- [ ] `handle()`: status `running` → `runExistingTrace(..., emitter: null)` → terminal (`completed`, `awaiting_input`, `failed`)
- [ ] `failed(Throwable)`: trace → `failed` + `error_message` + `finished_at`
- [ ] `$tries` / `$backoff` lidos de config
- [ ] Gate: `vendor/bin/phpunit --filter RunWorkflowJobTest`

**Tests**: unit (`tests/RunWorkflowJobTest.php` com `Queue::fake()` + workflow set_state simples)
**Gate**: quick

**Commit**: `feat(workflows): add RunWorkflowJob for async execution`

---

### T4: `WorkflowRunner::dispatch`

**What**: Criar trace `queued`, enfileirar job, retornar trace imediatamente.
**Where**: `src/Runtime/WorkflowRunner.php`
**Depends on**: T1, T3
**Reuses**: `WorkflowTrace::create`, `RunWorkflowJob::dispatch`
**Requirement**: QR-02, QR-03

**Done when**:

- [ ] `dispatch(WorkflowDefinition $workflow, array $input = []): WorkflowTrace` cria trace `{ status: queued, input, started_at: null }`
- [ ] Dispara `RunWorkflowJob` quando `async_runs_enabled === true`; lança `RuntimeException` claro se false (caller decide sync fallback)
- [ ] Retorna trace fresh com `id` para polling
- [ ] Gate: `vendor/bin/phpunit --filter WorkflowRunnerDispatchTest`

**Tests**: integration (`tests/WorkflowRunnerDispatchTest.php`)
**Gate**: quick

**Commit**: `feat(workflows): dispatch async workflow runs via queue`

---

### T5: `WorkflowRunController`

**What**: Endpoint HTTP que enfileira run e retorna JSON imediato.
**Where**: `src/Http/Controllers/WorkflowRunController.php`
**Depends on**: T4
**Reuses**: `ValidatesChatAttachments` (POST body igual ao stream), `WorkflowTraceController::traceSummary`
**Requirement**: QR-06

**Done when**:

- [ ] `POST` valida `message`, `state`, `thread_id`, `attachments` (mesmo contrato que `WorkflowStreamController`)
- [ ] Chama `$runner->dispatch($workflow, $payload)` quando async habilitado
- [ ] Resposta `{ trace_id, status: queued, thread_id? }` com HTTP 202
- [ ] Quando `async_runs_enabled` false → 501 ou 422 com mensagem acionável (documentar)
- [ ] Gate: `vendor/bin/phpunit --filter WorkflowRunControllerTest`

**Tests**: integration (`tests/WorkflowRunControllerTest.php`)
**Gate**: quick

**Commit**: `feat(studio): add POST endpoint to queue workflow runs`

---

### T6: Rotas async run

**What**: Registrar rota autenticada para dispatch assíncrono.
**Where**: `routes/web.php`
**Depends on**: T5
**Reuses**: Grupo `neuronai-studio.workflows.*`, middleware existente
**Requirement**: QR-06

**Done when**:

- [ ] `POST /workflows/{workflow}/run` → `WorkflowRunController` nome `workflows.run`
- [ ] Não conflita com `workflows.run.stream` (SSE síncrono existente)
- [ ] Gate: `vendor/bin/phpunit --filter WorkflowRunControllerTest`

**Tests**: coberto em T5
**Gate**: quick

**Commit**: `feat(studio): register async workflow run route`

---

### T7: Polling via trace JSON existente

**What**: Documentar e validar que `GET /workflows/traces/{trace}/json` serve polling de status (v1 — sem SSE em job).
**Where**: `src/Http/Controllers/WorkflowTraceController.php` (ajuste mínimo se necessário)
**Depends on**: T5, T6
**Reuses**: `WorkflowTraceController::show` já expõe `status`, `output`, `error_message`
**Requirement**: QR-07 (P1 — polling path)

**Done when**:

- [ ] Resposta JSON inclui `awaiting_node_id` quando `status === awaiting_input` (se ainda ausente)
- [ ] Status `queued` e `running` visíveis no summary
- [ ] Gate: `vendor/bin/phpunit --filter WorkflowTraceControllerTest`

**Tests**: estender `tests/WorkflowTraceControllerTest.php`
**Gate**: quick

**Commit**: `feat(studio): expose queued/running status in trace JSON for polling`

---

### T8: `ResumeWorkflowJob` (P1)

**What**: Enfileirar resume após Human HITL sem bloquear request HTTP.
**Where**: `src/Jobs/ResumeWorkflowJob.php`, `src/Runtime/WorkflowRunner.php` (`dispatchResume`)
**Depends on**: T3, T4
**Reuses**: `WorkflowRunner::resume`, padrão `RunWorkflowJob`
**Requirement**: QR-05

**Done when**:

- [ ] Job recebe `traceId`, `nodeId`, `message`, `attachments`
- [ ] `handle()` chama `resume(..., emitter: null)`; trace terminal atualizado
- [ ] `WorkflowRunner::dispatchResume(...)` enfileira quando async habilitado
- [ ] Gate: `vendor/bin/phpunit --filter ResumeWorkflowJobTest`

**Tests**: unit (`tests/ResumeWorkflowJobTest.php` com human node fixture)
**Gate**: full

**Commit**: `feat(workflows): add ResumeWorkflowJob for async HITL resume`

---

### T9: `POST /workflows/traces/{trace}/resume` (P1)

**What**: Endpoint JSON (non-stream) para enfileirar resume; SSE stream existente permanece.
**Where**: `src/Http/Controllers/WorkflowTraceResumeJsonController.php` (ou método no controller existente)
**Depends on**: T8
**Reuses**: Validação de `WorkflowTraceResumeController`
**Requirement**: QR-05

**Done when**:

- [ ] Body: `message`, `attachments`, `node_id` (default `awaiting_node_id` do trace)
- [ ] Retorna `{ trace_id, status: queued }` HTTP 202
- [ ] Rota `workflows.traces.resume` (sem `.stream`)
- [ ] Gate: teste dedicado ou extensão de controller test

**Tests**: integration
**Gate**: full

**Commit**: `feat(studio): add JSON endpoint to queue workflow resume`

---

### T10: Teste integração end-to-end (fake queue)

**What**: Fluxo completo: dispatch → process job → trace completed.
**Where**: `tests/WorkflowQueueRunnerTest.php`
**Depends on**: T3, T5, T7
**Reuses**: Grafo set_state de `WorkflowRunnerTest`, `Bus::fake()` / `Queue::fake()` + `Bus::dispatchSync` ou worker sync
**Requirement**: QR-08

**Done when**:

- [ ] `async_runs_enabled=true` no test env
- [ ] POST run → trace `queued` → job processado → trace `completed` com output esperado
- [ ] Falha simulada → trace `failed` + `error_message`
- [ ] Harness sync (`WorkflowStreamController`) inalterado quando async disabled
- [ ] Gate: `vendor/bin/phpunit --filter WorkflowQueueRunnerTest`

**Tests**: integration
**Gate**: full

**Commit**: `test(workflows): async queue runner end-to-end`

---

### T11: Documentação

**What**: Atualizar docs listados na spec; remover "reserved for future" em configuration.
**Where**: `docs/guides/workflows/runtime-and-traces.md`, `docs/guides/export-and-production.md`, `docs/reference/configuration.md`, `docs/reference/artisan-commands.md`, `docs/getting-started/installation.md`
**Depends on**: T10
**Reuses**: Estilo docs existentes (SSE / traces)
**Requirement**: spec Documentation table

**Done when**:

- [ ] Seção **Queue runner**: fluxo dispatch → poll → terminal states
- [ ] `async_runs_enabled`, `queue`, `queue_connection`, `queue_tries` documentados
- [ ] Nota `php artisan queue:work` no installation + artisan commands
- [ ] Decisão v1 documentada: job sem SSE; polling via `traces/{id}/json`

**Tests**: none
**Gate**: manual review

**Commit**: `docs(workflows): async queue runner and worker setup`

---

## Optional (defer)

| Item | Motivo |
|------|--------|
| Coluna `job_id` em `workflow_traces` | P2 observability; não bloqueia v1 |
| Toggle "Run in background" no harness JS | Fora do boundary M2/M3; API-first v1 |
| `GET /traces/{id}/stream` dedicado | Polling via JSON existente cobre QR-07 v1 |
| Broadcasting / Laravel Echo | Decisão em aberto no ROADMAP |

---

## Traceability

| Requirement | Tasks |
|-------------|-------|
| QR-01 | T1, T3 |
| QR-02 | T4 |
| QR-03 | T2, T3, T4 |
| QR-04 | T1, T3 |
| QR-05 | T8, T9 |
| QR-06 | T5, T6 |
| QR-07 | T7 |
| QR-08 | T10 |

---

## Agent handoff checklist

1. Branch: `feat/workflow-queue-runner` a partir de `v0.2.x`
2. Executar T1 → T11 em ordem das fases; T5/T6 podem paralelizar após T4
3. Não modificar canvas, codegen LLM/agent, nem structured output
4. Após merge: atualizar ROADMAP M3 status e STATE.md com snapshot M3
