# Tool Approval em Workflows — Tasks

**Design**: `.specs/features/workflow-tool-approval/design.md`
**Spec**: `.specs/features/workflow-tool-approval/spec.md`
**Status**: Done — M2 feature 5 (slices 1–3 entregues: backend, resume/API, UI+codegen+docs)

> **TESTING.md**: inexistente neste repositório. Matriz inferida dos padrões em `tests/`:
>
> | Camada | Tipo de teste | Comando gate | Parallel-Safe |
> |--------|---------------|--------------|---------------|
> | `src/Runtime/Exceptions/*` | unit | `vendor/bin/phpunit --filter {Class}Test` | Sim |
> | `AgentRunner` | unit/integration | `vendor/bin/phpunit --filter {Class}Test` | Não (DB para AgentDefinition) |
> | `WorkflowRunner` / controllers | integration | `vendor/bin/phpunit --filter {Class}Test` | Não (DB) |
> | Canvas / studio-chat JS | none | — | N/A (fatia 2) |
> | `docs/` | none | revisão manual | Sim |

---

## Slicing

| Fatia | Escopo | Requisitos |
|-------|--------|------------|
| Slice 1 ✅ | Backend: pausa em tool call, exceção, checkpoint, config, testes | TA-01, TA-02, TA-03, TA-04, TA-09 (backend) |
| **Slice 2 ✅ (esta)** | Resume approve/reject (interpreted), API/SSE, rejected handle | TA-05, TA-07 |
| Slice 3 ✅ | StudioChat `ToolApprovalCard` + codegen middleware + docs | TA-06, TA-08, docs |

---

## Execution Plan (Slice 1)

### Phase 1: Contract (Sequential)

```
T1 → T2
```

### Phase 2: Runner integration (Sequential)

```
T2 → T3 → T4 → T5
```

### Phase 3: Tests (após T5)

```
T5 → T6
```

---

## Task Breakdown

### T1: `ToolApprovalRequiredException`

**What**: Exceção de runtime carregando `node_id`, ferramentas pendentes (name, arguments, call_id) e mensagem de aprovação.
**Where**: `src/Runtime/Exceptions/ToolApprovalRequiredException.php`
**Depends on**: None
**Reuses**: Padrão de `HumanInputRequiredException`
**Requirement**: TA-03

**Done when**:

- [x] Construtor com `string $nodeId`, `array $pendingTools`, `string $approvalMessage`
- [x] `pendingTools` = `list<array{name: string, arguments: array, call_id: ?string}>`
- [x] Estende `RuntimeException`, mensagem = `approvalMessage`
- [x] Gate: `vendor/bin/phpunit --filter ToolApprovalRequiredExceptionTest`

**Tests**: unit
**Gate**: quick

**Commit**: `feat(workflows): add ToolApprovalRequiredException`

---

### T2: Config `require_tool_approval` no `AgentDefinition`

**What**: Coluna booleana + cast + fillable para habilitar aprovação por agente.
**Where**: `database/migrations/*_add_require_tool_approval_to_agent_definitions_table.php`, `src/Models/AgentDefinition.php`
**Depends on**: None
**Reuses**: Convenções de migration/cast existentes
**Requirement**: TA-01

**Done when**:

- [x] Coluna `require_tool_approval` boolean default `false` (nullable-safe)
- [x] `fillable` + cast `boolean`
- [x] Gate: `vendor/bin/phpunit --filter WorkflowToolApprovalTest`

**Tests**: coberto em T6
**Gate**: quick

**Commit**: `feat(agents): add require_tool_approval flag to AgentDefinition`

---

### T3: `AgentRunner` aplica `ToolApproval` middleware

**What**: Quando `require_tool_approval` no config, registrar `new ToolApproval()` no `DynamicAgent`; capturar `WorkflowInterrupt` (ApprovalRequest) em `runInline` e converter para `ToolApprovalRequiredException`.
**Where**: `src/Runtime/AgentRunner.php`
**Depends on**: T1
**Reuses**: `makeAgent`, `addGlobalMiddleware` (Neuron), `ApprovalRequest`/`ToolCallEvent`
**Requirement**: TA-02, TA-03

**Done when**:

- [x] `makeAgent` adiciona middleware quando `config['require_tool_approval'] === true`
- [x] `run()` e `resolveAgent()` propagam a flag do `AgentDefinition`
- [x] `runInline` captura `WorkflowInterrupt` com `ApprovalRequest` → lança `ToolApprovalRequiredException` (node_id vazio nesta camada)
- [x] Interrupts não-approval re-propagam intactos
- [x] Gate: `vendor/bin/phpunit --filter AgentRunnerToolApprovalTest`

**Tests**: unit (`tests/AgentRunnerToolApprovalTest.php`)
**Gate**: quick

**Commit**: `feat(workflows): apply ToolApproval middleware in AgentRunner`

---

### T4: `AgentNodeExecutor` anexa `node_id`

**What**: Passar flag para o config e re-lançar `ToolApprovalRequiredException` com o `node_id` do grafo.
**Where**: `src/Runtime/NodeExecutors/AgentNodeExecutor.php`
**Depends on**: T3
**Reuses**: Resolução de `agent_id` + `data` override existente
**Requirement**: TA-01, TA-02

**Done when**:

- [x] Config inclui `require_tool_approval` de `data.require_tool_approval` (override) ou `definition->require_tool_approval`
- [x] Captura `ToolApprovalRequiredException` do runner e re-lança com `node_id` do nó
- [x] Path structured/inline preservados
- [x] Gate: `vendor/bin/phpunit --filter WorkflowToolApprovalTest`

**Tests**: coberto em T6
**Gate**: quick

**Commit**: `feat(workflows): carry node id on tool approval pause`

---

### T5: `WorkflowRunner::pauseForToolApproval`

**What**: Capturar `ToolApprovalRequiredException` em `runInterpreted`/`resumeInterpreted`; persistir checkpoint `awaiting_tool_approval` e emitir SSE `tool_approval_required`.
**Where**: `src/Runtime/WorkflowRunner.php`
**Depends on**: T1, T4
**Reuses**: Padrão `pauseForHumanInput`
**Requirement**: TA-04

**Done when**:

- [x] `catch (ToolApprovalRequiredException)` em `runInterpreted` e `resumeInterpreted`
- [x] `pauseForToolApproval` seta `status = awaiting_tool_approval`, `awaiting_node_id`, `checkpoint = { state, node_id, pending_tools }`, `finished_at = null`
- [x] Emite `tool_approval_required` `{ trace_id, node_id, pending_tools, message }` quando há emitter
- [x] Status `awaiting_tool_approval` distinto de `awaiting_input` (sem migration — coluna string)
- [x] Gate: `vendor/bin/phpunit --filter WorkflowToolApprovalTest`

**Tests**: coberto em T6
**Gate**: quick

**Commit**: `feat(workflows): checkpoint awaiting_tool_approval on tool pause`

---

### T6: Testes backend slice 1

**What**: Cobrir exceção, runner (interrupt → exceção) e integração workflow (agent + tool → pausa).
**Where**: `tests/ToolApprovalRequiredExceptionTest.php`, `tests/AgentRunnerToolApprovalTest.php`, `tests/WorkflowToolApprovalTest.php`
**Depends on**: T1–T5
**Reuses**: `FakeAIProvider`, `ToolCallMessage`, `Tool`, padrão de wiring de `AutonomousMultimodalAgentsTest`
**Requirement**: TA-09 (backend)

**Done when**:

- [x] Unit: exceção guarda node_id/pendingTools/message
- [x] Unit: `runInline` com approval + `ToolCallMessage` → `ToolApprovalRequiredException` com tool name/args
- [x] Integração: workflow start→agent→stop com approval on → trace `awaiting_tool_approval` + checkpoint `pending_tools`
- [x] Sem approval → workflow completa normalmente (regressão)
- [x] Gate: `vendor/bin/phpunit` (suíte verde)

**Tests**: unit + integration
**Gate**: full

**Commit**: `test(workflows): backend tool approval pause slice`

---

## Slice 2 (resume + API/SSE) — entregue

| Task | Requisito | Status |
|------|-----------|--------|
| `ToolApprovalRequiredException` carrega `serializedInterrupt` | TA-05 | [x] |
| `AgentRunner::toolApprovalException` serializa `WorkflowInterrupt` | TA-05 | [x] |
| `AgentRunner::resumeInlineApproval` (restaura interrupt + aplica decisão + resume) | TA-05 | [x] |
| `AgentNodeExecutor` consome marker `__tool_approval_resume` + roteia `rejected` | TA-05, TA-07 | [x] |
| `WorkflowRunner::pauseForToolApproval` persiste `interrupt` no checkpoint | TA-05 | [x] |
| `WorkflowRunner::resumeInterpreted`/`resumeToolApproval` (`approval`) + SSE `tool_approval_resolved` | TA-05 | [x] |
| Controllers sync/async aceitam `approval: approve\|reject` (message opcional) | TA-05 | [x] |
| `dispatchResume`/`ResumeWorkflowJob` propagam `approval` | TA-05 | [x] |
| Testes approve/reject + handle `rejected` (`WorkflowToolApprovalTest`) | TA-09 | [x] |

**Gate**: `vendor/bin/phpunit` — suíte 233 verde.

---

## Slice 3 (UI + codegen + docs) — entregue

| Task | Requisito | Status |
|------|-----------|--------|
| `WorkflowSessionAdapter` guarda `pendingApproval` (SSE `tool_approval_required`) + `resumeApproval(decision, feedback)` (`{ approval }`) | TA-06 | [x] |
| `StudioChat` trata `tool_approval_required` → card inline + `handleToolApproval` consome resume stream; loop extraído em `consumeAssistantStream` | TA-06 | [x] |
| `ToolApprovalCard.jsx` — tools pendentes + args + Approve/Reject + feedback opcional (sem modal) | TA-06 | [x] |
| `MessageList` renderiza `ToolApprovalCard` + badge `Tool approval` (`awaiting_tool_approval`) | TA-06 | [x] |
| `AgentNodeCodeGenerator` — `require_tool_approval` no `runInline` (definition/override) + `addGlobalMiddleware(new ToolApproval())` no path inline | TA-08 | [x] |
| Rebuild `studio-chat.bundle.js` (Vite IIFE) | — | [x] |
| Docs: human-in-the-loop, ai-nodes, creating-agents, runtime-and-traces, security-and-access | docs | [x] |
| Testes codegen (`NativeWorkflowExporterTest` — definition flag + inline middleware) | TA-08/TA-09 | [x] |

**Gate**: `vendor/bin/phpunit` — suíte verde (235).

---

## Deferred (P1+)

| Item | Motivo |
|------|--------|
| Lista de tools específicas (`ToolApproval([...])`) e condicionais | P1 — slices 1–3 aprovam todas as tools |
| Campo `require_tool_approval` no inspector do canvas (override por nó via UI) | Atualmente override só via JSON do nó; flag no AgentDefinition tem UI |

---

## Traceability (Slice 1)

| Requirement | Tasks |
|-------------|-------|
| TA-01 | T2, T4 |
| TA-02 | T3, T4 |
| TA-03 | T1, T3 |
| TA-04 | T5 |
| TA-09 (backend) | T6 |

---

## Agent handoff checklist

1. Branch: `v0.2.x` (feature em progresso)
2. Executar T1 → T6 em ordem
3. Slice 1 é backend-only; não tocar canvas / studio-chat / codegen
4. Após slice 1: atualizar STATE.md + ROADMAP.md com snapshot parcial
5. Slice 2 = resume + API/SSE; Slice 3 = UI + codegen + docs
