# Unified Runs and Traces Tasks

**Design**: `.specs/features/unified-runs-and-traces/design.md`
**Status**: Completed

---

## Execution Plan

### Phase 1: Foundation (Sequential)

T1 → T2

### Phase 2: Core Implementation (Parallel OK)

```
     ┌→ T3 ─┐
T2 ──┼→ T4 ─┼──→ T6
     └→ T5 ─┘
```

### Phase 3: Integration (Sequential)

T6 → T7

---

## Task Breakdown

### T1: [Limpeza de Migrations e Models Legados]

**What**: Apagar migrations e models referentes ao legado de workflow traces e checkpoints.
**Where**: `database/migrations/`, `src/Models/WorkflowTrace.php`, `src/Models/WorkflowTraceStep.php`, `src/Models/WorkflowCheckpoint.php`
**Depends on**: None
**Reuses**: N/A
**Requirement**: CORE-01

**Done when**:
- [x] Arquivos de migrations antigos deletados (checkpoints, traces, update_runs_to_traces).
- [x] Models `WorkflowTrace`, `WorkflowTraceStep`, `WorkflowCheckpoint` apagados.
- [x] Gate check passes (phpstan/phpunit).

**Tests**: none
**Gate**: quick

---

### T2: [Criar Novas Migrations e Models]

**What**: Criar as novas tabelas unificadas (`threads`, `runs`, `traces`, `trace_spans`) com suporte a tokens.
**Where**: `database/migrations/`, `src/Models/` (StudioThread, StudioRun, StudioTrace, StudioTraceSpan)
**Depends on**: T1
**Reuses**: N/A
**Requirement**: CORE-01, CORE-02

**Done when**:
- [x] Migration criada contendo as 4 novas tabelas, utilizando o prefixo dinâmico de `StudioTables::name()`.
- [x] Os 4 Models Eloquent criados com seus devidos relacionamentos e propriedades `$fillable`, `$casts`.
- [x] Colunas `prompt_tokens`, `completion_tokens`, `total_tokens` incluídas em `runs` e `trace_spans`.

**Tests**: none
**Gate**: quick

---

### T3: [Refatorar AgentRunner para usar StudioRun e StudioTrace] [P]

**What**: Modificar o `AgentRunner` para gravar sua execução no banco de dados e repassar tokens.
**Where**: `src/Runtime/AgentRunner.php`
**Depends on**: T2
**Reuses**: Lógica atual, apenas trocando o persistence para Eloquent.
**Requirement**: CORE-01, CORE-02

**Done when**:
- [x] AgentRunner cria um `StudioThread` e um `StudioRun` ao iniciar.
- [x] `InMemoryPersistence` substituído pela atualização da coluna `checkpoint_state` em `StudioRun` quando precisar de tool approval.
- [x] Chamadas ao provedor extraem dados de uso e gravam `StudioTraceSpans`.
- [x] Agregação de tokens finalizada salva em `StudioRun` ao término.

**Tests**: unit
**Gate**: quick

---

### T4: [Refatorar WorkflowRunner para usar StudioRun e StudioTrace] [P]

**What**: Modificar a máquina de estado do Workflow para usar as novas entidades.
**Where**: `src/Runtime/WorkflowRunner.php`, `src/Runtime/Checkpoint/CheckpointService.php`
**Depends on**: T2
**Reuses**: Lógica atual, renomeando referencias de `WorkflowTrace` para `StudioRun`.
**Requirement**: CORE-01, CORE-02

**Done when**:
- [x] WorkflowRunner salva seu estado em `StudioRun`.
- [x] `CheckpointService` salva em `StudioRun.checkpoint_state` em vez da antiga tabela `workflow_checkpoints`.
- [x] Spans do workflow gravam corretamente no banco.

**Tests**: unit
**Gate**: quick

---

### T5: [Atualizar Relacionamento do StudioChatMessage] [P]

**What**: Migrar a tabela `chat_messages` para usar o `thread_id` real.
**Where**: `database/migrations/`, `src/Models/StudioChatMessage.php`, `src/Services/ChatThreadLoader.php`
**Depends on**: T2
**Requirement**: CORE-01

**Done when**:
- [x] Migration que altera o tipo da coluna `thread_id` em `chat_messages` para UUID, caso fosse string. (Ou apenas garantir que grava e pesquisa o UUID do `StudioThread`).
- [x] `ChatThreadLoader` e `AgentChatThreadController` usam o novo `StudioThread`.

**Tests**: unit
**Gate**: quick

---

### T6: [Refatorar Endpoints de Integração (Resume)]

**What**: Criar a nova rota `/threads/{thread}/runs/{run}/resume/{protocol}` e remover antigas.
**Where**: `src/Http/Controllers/Integration/`, `routes/api.php` ou `routes/integration.php`
**Depends on**: T3, T4
**Reuses**: N/A
**Requirement**: CORE-03

**Done when**:
- [x] Rota antiga `/traces/.../resume` substituída pela rota de `threads/.../runs/.../resume`.
- [x] Controllers atualizados para buscar o `StudioRun` via Eloquent e despachar o Job ou invocar o Resume inline.

**Tests**: e2e
**Gate**: full

---

### T7: [Atualizar Neuron Debugger / Studio UI Output]

**What**: Ajustar as respostas de traces para incluir as chaves de `tokens` e não quebrar a UI.
**Where**: `src/Http/Controllers/WorkflowTraceController.php` (ou similar)
**Depends on**: T6
**Reuses**: N/A
**Requirement**: CORE-02

**Done when**:
- [x] Endpoints JSON usados pelo Studio expõem as variáveis de `prompt_tokens`, `completion_tokens` e `total_tokens`.
- [x] Controllers que listavam os `WorkflowTraces` agora listam `StudioRuns` e seus respectivos `StudioTraces`.

**Tests**: none
**Gate**: build

---

## Parallel Execution Map

```
Phase 1 (Sequential):
  T1 ──→ T2

Phase 2 (Parallel):
  T2 complete, then:
    ├── T3 [P]
    ├── T4 [P]
    └── T5 [P]

Phase 3 (Sequential):
  T3, T4, T5 complete, then:
    T6 ──→ T7
```

## Task Granularity Check

| Task | Scope | Status |
| --- | --- | --- |
| T1: Legados | 4 models | ✅ Granular |
| T2: Novas migrations | 4 models | ✅ Granular |
| T3: AgentRunner | 1 class | ✅ Granular |
| T4: WorkflowRunner | 1 class | ✅ Granular |
| T5: Chat Messages | 1-2 classes | ✅ Granular |
| T6: Resume Endpoints | API | ✅ Granular |
| T7: UI API JSON | API | ✅ Granular |

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | -------------------------- | ----------------------- |
| T1 | None | None | ✅ Match |
| T2 | T1 | T1 | ✅ Match |
| T3 | T2 | T2 | ✅ Match |
| T4 | T2 | T2 | ✅ Match |
| T5 | T2 | T2 | ✅ Match |
| T6 | T3, T4, T5 | T3, T4, T5 | ✅ Match |
| T7 | T6 | T6 | ✅ Match |
