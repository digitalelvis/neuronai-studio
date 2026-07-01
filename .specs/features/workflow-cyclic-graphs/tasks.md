# Grafos Cíclicos em Workflows — Tasks

**Design**: `.specs/features/workflow-cyclic-graphs/design.md`
**Spec**: `.specs/features/workflow-cyclic-graphs/spec.md`
**Status**: Approved

> **TESTING.md**: inexistente neste repositório. Matriz inferida dos padrões em `tests/` e `composer.json`:
>
> | Camada | Tipo de teste | Comando gate | Parallel-Safe |
> |--------|---------------|--------------|---------------|
> | `src/Runtime/*` (classes isoladas) | unit | `vendor/bin/phpunit --filter {Class}Test` | Sim |
> | `WorkflowRunner` / templates / harness | integration | `vendor/bin/phpunit --filter {Class}Test` | Não (DB compartilhado) |
> | Canvas JS (`resources/js/studio-canvas/`) | none (build) | `BUILD_TARGET=canvas npm run build` | Sim |
> | Config / wiring apenas | none | gate do consumidor | Sim |
> | `docs/` | none | revisão manual | Sim |

---

## Execution Plan

### Phase 1: Foundation (Sequential)

Configuração e validação de ciclos — base para runtime e canvas.

```
T1 → T2 → T3 → T4
```

### Phase 2: Core Backend (Sequential)

Executor, registro e guardrails de execução.

```
T4 ──→ T5 → T6 → T7
```

### Phase 3: Frontend Canvas (Parallel OK)

UI do nó `loop` após config registrada (palette auto-descobre `node_types`).

```
T1 ──┬→ T8 [P]
     ├→ T9 [P]
     ├→ T10 [P]
     └→ T11 [P]
```

### Phase 4: Template / Integration (Sequential)

Testes de integração LOOP-10 e template de referência.

```
T7 + T8..T11 ──→ T12 → T13 → T14 → T15
```

### Phase 5: P1 — Codegen e Inspector (Parallel OK)

Export Neuron e iteração visível no harness (requisitos P1).

```
T5 ──→ T16 [P] ──→ T17 → T18
T7 + T8 ──→ T19 [P]
```

### Phase 6: Docs (Parallel OK)

Documentação de usuário e extensão.

```
T14 ──┬→ T20 [P]
      └→ T21 [P]
```

---

## Task Breakdown

### T1: Registrar config do nó `loop`

**What**: Adicionar `node_types.loop` e chaves `loop.default_max_steps` (e opcional `loop.global_max_steps`) em config.
**Where**: `config/neuronai-studio.php`
**Depends on**: None
**Reuses**: Entrada `condition` em `node_types` (label, icon, category `logic`)
**Requirement**: LOOP-01, LOOP-02

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [ ] `node_types.loop` com `label`, `icon` (`repeat` ou similar), `category` => `logic`
- [ ] `loop.default_max_steps` com default sensato (ex.: `10`)
- [ ] Gate check passes: `vendor/bin/phpunit --filter GraphValidatorTest`

**Tests**: none
**Gate**: quick

**Verify**:

```bash
php -r "require 'vendor/autoload.php'; var_export(config('neuronai-studio.node_types.loop'));"
```

Saída contém `loop` e `logic`.

**Commit**: `feat(workflows): add loop node type config`

---

### T2: Criar `MaxLoopIterationsException`

**What**: Exceção de runtime com mensagem identificável para estouro de `max_steps`.
**Where**: `src/Runtime/Exceptions/MaxLoopIterationsException.php`
**Depends on**: None
**Reuses**: Padrão de exceções em `src/Runtime/`
**Requirement**: LOOP-03

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [ ] Classe estende `\RuntimeException` (ou exceção de domínio existente)
- [ ] Mensagem inclui `node_id`, `iteration` e `max_steps`
- [ ] Gate check passes: `php -l src/Runtime/Exceptions/MaxLoopIterationsException.php`

**Tests**: unit (assertions em `LoopNodeExecutorTest`, T5)
**Gate**: quick

**Verify**:

```bash
php -l src/Runtime/Exceptions/MaxLoopIterationsException.php
```

Saída: `No syntax errors detected`.

**Commit**: `feat(workflows): add MaxLoopIterationsException`

---

### T3: Implementar `CycleDetector`

**What**: DFS com classificação de back-edges em grafo dirigido de workflow.
**Where**: `src/Runtime/CycleDetector.php`, `tests/CycleDetectorTest.php`
**Depends on**: T1
**Reuses**: Padrão de adjacência de `GraphValidator::canReachStop`
**Requirement**: LOOP-04, LOOP-05

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [ ] `hasCycle(array $nodes, array $edges): bool` detecta ciclos
- [ ] `unauthorizedBackEdges(...)` retorna arestas não autorizadas sem nó `loop` + `max_steps`
- [ ] Gate check passes: `vendor/bin/phpunit --filter CycleDetectorTest`
- [ ] Test count: ≥ 4 testes passam (sem deleções silenciosas)

**Tests**: unit
**Gate**: quick

**Verify**:

```bash
vendor/bin/phpunit --filter CycleDetectorTest
```

**Commit**: `feat(workflows): add CycleDetector for workflow graphs`

---

### T4: Estender `GraphValidator` para grafos cíclicos

**What**: Integrar `CycleDetector`; rejeitar back-edges não autorizados; relaxar `canReachStop` para subgrafos com `loop` + `max_steps` > 0.
**Where**: `src/Runtime/GraphValidator.php`, `tests/GraphValidatorTest.php`
**Depends on**: T3
**Reuses**: `GraphValidator` existente, `CycleDetector`
**Requirement**: LOOP-04, LOOP-05, LOOP-10

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [ ] Grafo cíclico sem nó `loop` falha com mensagem acionável (`Cyclic graph requires a loop node with max_steps.`)
- [ ] Grafo com `loop` e `max_steps` > 0 valida com back-edge no corpo
- [ ] Regras existentes (start único, stop, tipos registrados) preservadas
- [ ] Gate check passes: `vendor/bin/phpunit --filter GraphValidatorTest`
- [ ] Test count: ≥ 6 testes passam (sem deleções silenciosas)

**Tests**: unit
**Gate**: quick

**Verify**:

```bash
vendor/bin/phpunit --filter GraphValidatorTest
```

**Commit**: `feat(workflows): validate cyclic graphs with loop guardrails`

---

### T5: Implementar `LoopNodeExecutor`

**What**: Incrementar `__loop_iterations.{nodeId}`, avaliar condição de saída, rotear handles `continue` / `exit`.
**Where**: `src/Runtime/NodeExecutors/LoopNodeExecutor.php`, `tests/LoopNodeExecutorTest.php`
**Depends on**: T1, T2
**Reuses**: Operadores de `ConditionNodeExecutor`, `MaxLoopIterationsException`
**Requirement**: LOOP-02, LOOP-03, LOOP-06, LOOP-10

**Tools**:

- MCP: NONE
- Skill: `neuron-workflow-architect`

**Done when**:

- [ ] Contador em `__loop_iterations.{nodeId}` incrementado a cada passagem
- [ ] `max_steps` lido de `data.max_steps` com fallback em `config('neuronai-studio.loop.default_max_steps')`
- [ ] Condição (`state_key`, `operator`, `value`) roteia `exit` quando satisfeita, senão `continue`
- [ ] Estouro de `max_steps` lança `MaxLoopIterationsException` ou roteia `exit` se handle existir (conforme design)
- [ ] Gate check passes: `vendor/bin/phpunit --filter LoopNodeExecutorTest`
- [ ] Test count: ≥ 8 testes passam (sem deleções silenciosas)

**Tests**: unit
**Gate**: quick

**Verify**:

```bash
vendor/bin/phpunit --filter LoopNodeExecutorTest
```

**Commit**: `feat(workflows): add LoopNodeExecutor`

---

### T6: Registrar `loop` no ServiceProvider

**What**: Registrar tipo `loop` em `NodeTypeRegistry` e `NodeExecutorRegistry`.
**Where**: `src/NeuronAIStudioServiceProvider.php`
**Depends on**: T5
**Reuses**: Bloco `registerNodeTypes()` existente
**Requirement**: LOOP-01

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [ ] `'loop' => LoopNodeExecutor::class` no mapa de tipos
- [ ] `NodeTypeRegistry::has('loop')` retorna true em runtime
- [ ] Gate check passes: `vendor/bin/phpunit --filter LoopNodeExecutorTest`

**Tests**: none
**Gate**: quick

**Verify**:

```bash
vendor/bin/phpunit --filter LoopNodeExecutorTest
```

**Commit**: `feat(workflows): register loop node executor`

---

### T7: Guardrails e eventos `loop_iteration` em `GraphExecutionLoop`

**What**: Emitir SSE `loop_iteration`; incluir `iteration` em steps de nó `loop`; guardrail global opcional via config.
**Where**: `src/Runtime/GraphExecutionLoop.php`, `tests/GraphExecutionLoopTest.php`
**Depends on**: T5, T6
**Reuses**: `emitStep()` em `BuilderWorkflowState`, padrão `step_started` / `step_completed`
**Requirement**: LOOP-03, LOOP-06, LOOP-10

**Tools**:

- MCP: NONE
- Skill: `neuron-debugger`

**Done when**:

- [ ] Evento `loop_iteration` com payload `{ node_id, iteration, max_steps }` a cada passagem pelo nó `loop`
- [ ] `step_started` / `step_completed` incluem `iteration` quando `node_type === 'loop'`
- [ ] Execução nunca entra em loop infinito — termina por `stop`, `exit` ou `max_steps`
- [ ] Gate check passes: `vendor/bin/phpunit --filter GraphExecutionLoopTest`
- [ ] Test count: ≥ 5 testes passam (sem deleções silenciosas)

**Tests**: unit
**Gate**: quick

**Verify**:

```bash
vendor/bin/phpunit --filter GraphExecutionLoopTest
```

**Commit**: `feat(workflows): emit loop_iteration events in GraphExecutionLoop`

---

### T8: Handles `continue` / `exit` no canvas [P]

**What**: Renderizar handles `default` (entrada), `continue` e `exit` no nó `loop`.
**Where**: `resources/js/studio-canvas/nodes/WorkflowNode.jsx`, `resources/js/studio-canvas/canvas.css`
**Depends on**: T1
**Reuses**: Padrão de handles do nó `condition` em `WorkflowNode.jsx`
**Requirement**: LOOP-01

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [ ] Handles `default`, `continue`, `exit` posicionados e estilizados
- [ ] Labels visíveis `continue` / `exit` no nó
- [ ] Ícone `repeat` (ou equivalente) em `ICONS`
- [ ] Gate check passes: `BUILD_TARGET=canvas npm run build`

**Tests**: none
**Gate**: build

**Verify**:

```bash
BUILD_TARGET=canvas npm run build
```

Bundle compila sem erros.

**Commit**: `feat(canvas): add loop node handles`

---

### T9: Estilos de aresta para handles `continue` / `exit` [P]

**What**: Labels e cores distintas para arestas `continue` e `exit`.
**Where**: `resources/js/studio-canvas/graph.js`, `resources/js/studio-canvas/edges/WorkflowEdge.jsx` (se necessário)
**Depends on**: T1
**Reuses**: `edgeLabelForHandle` / `edgeStyleForHandle` para `true`/`false`
**Requirement**: LOOP-01

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [ ] `edgeLabelForHandle('continue')` e `edgeLabelForHandle('exit')` retornam labels
- [ ] Estilos visuais distinguem `continue` (corpo do loop) de `exit` (saída)
- [ ] Round-trip JSON preserva `sourceHandle` `continue`/`exit`
- [ ] Gate check passes: `BUILD_TARGET=canvas npm run build`

**Tests**: none
**Gate**: build

**Verify**:

```bash
BUILD_TARGET=canvas npm run build
```

**Commit**: `feat(canvas): style continue and exit loop edges`

---

### T10: Campos de configuração do loop no inspector [P]

**What**: Formulário para `max_steps`, `state_key`, `operator`, `value` no inspector.
**Where**: `resources/js/studio-canvas/inspector/NodeConfigForm.jsx`
**Depends on**: T1
**Reuses**: Bloco `condition` em `NodeConfigForm.jsx`
**Requirement**: LOOP-02

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [ ] Campo `max_steps` numérico com placeholder do default global
- [ ] Campos de condição reutilizam operadores do nó `condition`
- [ ] Valores persistem em `node.data.config` no JSON do grafo
- [ ] Gate check passes: `BUILD_TARGET=canvas npm run build`

**Tests**: none
**Gate**: build

**Verify**:

```bash
BUILD_TARGET=canvas npm run build
```

**Commit**: `feat(canvas): add loop node inspector fields`

---

### T11: Defaults do nó `loop` em `nodeUtils` [P]

**What**: Normalizar dados padrão ao editar nó `loop` recém-criado.
**Where**: `resources/js/studio-canvas/inspector/nodeUtils.js`
**Depends on**: T1
**Reuses**: Defaults de `condition` em `normalizeNodeForEdit`
**Requirement**: LOOP-02

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [ ] `max_steps` default ao abrir editor (vazio = usar config global)
- [ ] `operator` default `not_empty`, `state_key` default `input`
- [ ] Gate check passes: `BUILD_TARGET=canvas npm run build`

**Tests**: none
**Gate**: build

**Verify**:

```bash
BUILD_TARGET=canvas npm run build
```

**Commit**: `feat(canvas): normalize loop node defaults`

---

### T12: Testes de integração — execução de loop e `max_steps`

**What**: Workflow interpretado com loop: saída por condição, falha por `max_steps`, eventos SSE.
**Where**: `tests/WorkflowLoopTest.php`
**Depends on**: T4, T5, T7
**Reuses**: Helpers de `ConditionNodeExecutorTest`, padrão de eventos em `WorkflowRunnerTest`
**Requirement**: LOOP-03, LOOP-10

**Tools**:

- MCP: NONE
- Skill: `neuron-test-engineer`

**Done when**:

- [ ] Loop sai por condição `exit` com trace `completed`
- [ ] Estouro de `max_steps` produz trace `failed` com mensagem identificável
- [ ] Callback de eventos captura `loop_iteration` com `iteration` crescente
- [ ] Gate check passes: `vendor/bin/phpunit --filter WorkflowLoopTest`
- [ ] Test count: ≥ 3 testes passam (sem deleções silenciosas)

**Tests**: integration
**Gate**: quick

**Verify**:

```bash
vendor/bin/phpunit --filter WorkflowLoopTest
```

**Commit**: `test(workflows): add loop execution and max_steps integration tests`

---

### T13: Teste de integração — resume HITL dentro de loop

**What**: Workflow com `human` no corpo do loop: pausa, resume e completa sem perder contador de iteração.
**Where**: `tests/WorkflowLoopTest.php` (adicionar casos)
**Depends on**: T12
**Reuses**: `StudioTestHarnessTest::test_human_node_pauses_and_resumes_trace`
**Requirement**: LOOP-10

**Tools**:

- MCP: NONE
- Skill: `neuron-test-engineer`

**Done when**:

- [ ] Trace pausa em `awaiting_input` dentro do corpo do loop
- [ ] `resume()` continua iteração e completa com estado correto
- [ ] `__loop_iterations.{nodeId}` reflete iterações após resume
- [ ] Gate check passes: `vendor/bin/phpunit --filter WorkflowLoopTest`
- [ ] Test count: ≥ 4 testes passam no arquivo (sem deleções silenciosas)

**Tests**: integration
**Gate**: quick

**Verify**:

```bash
vendor/bin/phpunit --filter WorkflowLoopTest
```

**Commit**: `test(workflows): add HITL resume inside loop test`

---

### T14: Template `lead-qualification-loop`

**What**: Template versionado demonstrando re-parse até email válido ou `max_steps`.
**Where**: `resources/templates/workflows/lead-qualification-loop.json`, `tests/TemplateRegistryTest.php`, `tests/TemplateInstallerTest.php`
**Depends on**: T4, T8
**Reuses**: `resources/templates/workflows/lead-qualification.json`, `TemplateInstaller`
**Requirement**: LOOP-07, LOOP-10

**Tools**:

- MCP: NONE
- Skill: `neuron-workflow-architect`

**Done when**:

- [ ] JSON com meta `id: lead-qualification-loop` e grafo com nó `loop`
- [ ] `TemplateInstaller::installWorkflow('lead-qualification-loop')` cria workflow válido
- [ ] Grafo instalado passa `GraphValidator`
- [ ] `TemplateRegistryTest` atualizado (contagem de templates)
- [ ] Gate check passes: `vendor/bin/phpunit --filter 'TemplateInstallerTest|TemplateRegistryTest'`
- [ ] Test count: testes de template passam (sem deleções silenciosas)

**Tests**: integration
**Gate**: quick

**Verify**:

```bash
vendor/bin/phpunit --filter 'TemplateInstallerTest|TemplateRegistryTest'
```

**Commit**: `feat(templates): add lead-qualification-loop workflow`

---

### T15: Gate final P0 — suite PHPUnit

**What**: Confirmar que T1–T14 não quebraram regressões; marcar P0 pronto para merge.
**Where**: —
**Depends on**: T14
**Reuses**: `composer test`
**Requirement**: LOOP-10

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [ ] Gate check passes: `composer test`
- [ ] Nenhum teste removido silenciosamente (contagem ≥ baseline pré-feature)

**Tests**: integration (full suite)
**Gate**: full

**Verify**:

```bash
composer test
```

**Commit**: `chore(workflows): verify cyclic graphs P0 test suite`

---

### T16: `LoopNodeCodeGenerator` [P]

**What**: Gerar corpo PHP Neuron com contador de iteração e branch `continue`/`exit`.
**Where**: `src/Codegen/NodeCodeGenerators/LoopNodeCodeGenerator.php`, `src/Codegen/NodeCodeGenerators/NodeCodeGeneratorRegistry.php`
**Depends on**: T5
**Reuses**: `ConditionNodeCodeGenerator`, padrão `branchReturns`
**Requirement**: LOOP-08

**Tools**:

- MCP: NONE
- Skill: `neuron-workflow-architect`

**Done when**:

- [ ] `supports('loop')` retorna true
- [ ] Código gerado incrementa contador e compara `max_steps`
- [ ] Registrado em `NodeCodeGeneratorRegistry`
- [ ] Gate check passes: `vendor/bin/phpunit --filter NativeWorkflowExporterTest`

**Tests**: unit (via exporter)
**Gate**: quick

**Verify**:

```bash
vendor/bin/phpunit --filter NativeWorkflowExporterTest
```

**Commit**: `feat(codegen): add LoopNodeCodeGenerator`

---

### T17: Mapear handles `continue`/`exit` no `GraphTranspiler`

**What**: Branch returns e eventos tipados para export de grafos com loop.
**Where**: `src/Codegen/GraphTranspiler.php`
**Depends on**: T16
**Reuses**: Bloco `condition` em `GraphTranspiler::transpile`
**Requirement**: LOOP-08

**Tools**:

- MCP: NONE
- Skill: `neuron-workflow-architect`

**Done when**:

- [ ] Handles `continue` e `exit` mapeados para eventos distintos (ex.: `LoopContinueEvent`, `LoopExitEvent`)
- [ ] Export de grafo com loop não lança `InvalidArgumentException`
- [ ] Gate check passes: `vendor/bin/phpunit --filter NativeWorkflowExporterTest`

**Tests**: unit (via exporter)
**Gate**: quick

**Verify**:

```bash
vendor/bin/phpunit --filter NativeWorkflowExporterTest
```

**Commit**: `feat(codegen): transpile loop node branches`

---

### T18: Teste de export do template com loop

**What**: `NativeWorkflowExporter` gera PHP compilável para `lead-qualification-loop`.
**Where**: `tests/NativeWorkflowExporterTest.php`
**Depends on**: T14, T17
**Reuses**: Casos existentes em `NativeWorkflowExporterTest`
**Requirement**: LOOP-08, LOOP-10

**Tools**:

- MCP: NONE
- Skill: `neuron-test-engineer`

**Done when**:

- [ ] `export()` / `preview()` do template com loop retorna classes sem erro
- [ ] Código gerado contém guardrail `max_steps`
- [ ] Gate check passes: `vendor/bin/phpunit --filter NativeWorkflowExporterTest`
- [ ] Test count: testes do exporter passam (sem deleções silenciosas)

**Tests**: integration
**Gate**: quick

**Verify**:

```bash
vendor/bin/phpunit --filter NativeWorkflowExporterTest
```

**Commit**: `test(codegen): export lead-qualification-loop with loop node`

---

### T19: Exibir iteração atual do loop no test harness [P]

**What**: Badge ou meta no nó `loop` durante execução ao receber `loop_iteration`.
**Where**: `resources/js/studio-canvas/WorkflowCanvas.jsx`, `resources/js/studio-canvas/nodes/WorkflowNode.jsx`
**Depends on**: T7, T8
**Reuses**: Handler `canvas-execution-event`, `executionStatus` em nós
**Requirement**: LOOP-09

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [ ] Evento `loop_iteration` atualiza UI do nó com `iteration / max_steps`
- [ ] Estado limpo em `canvas-run-start` / `trace_completed`
- [ ] Gate check passes: `BUILD_TARGET=canvas npm run build`

**Tests**: none
**Gate**: build

**Verify**:

```bash
BUILD_TARGET=canvas npm run build
```

**Commit**: `feat(canvas): show loop iteration during harness run`

---

### T20: Documentação de workflows — nó loop e ciclos [P]

**What**: Seções sobre nó `loop`, estado de iteração, grafos cíclicos e traces.
**Where**: `docs/guides/workflows/node-types/flow-nodes.md`, `docs/guides/workflows/state-and-conditions.md`, `docs/guides/workflows/overview.md`, `docs/guides/workflows/runtime-and-traces.md`
**Depends on**: T14
**Reuses**: Estrutura de docs de nós existentes (`condition`, `human`)
**Requirement**: — (spec Documentation table)

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [ ] `## Nó Loop` com handles, `max_steps`, diagrama
- [ ] `## Iterações de loop` com chaves `__loop_iterations`
- [ ] `## Grafos cíclicos` em overview
- [ ] `## Eventos loop_iteration` em runtime-and-traces

**Tests**: none
**Gate**: none

**Commit**: `docs(workflows): document loop node and cyclic graphs`

---

### T21: Documentação — template, config e extensão [P]

**What**: Template lead-qualification-loop, defaults de config e padrão de guardrails.
**Where**: `docs/guides/templates.md`, `docs/reference/configuration.md`, `docs/extending/custom-node-types.md`
**Depends on**: T14
**Reuses**: Entrada `lead-qualification` em `templates.md`
**Requirement**: — (spec Documentation table)

**Tools**:

- MCP: NONE
- Skill: NONE

**Done when**:

- [ ] `## Lead Qualification (loop)` com quando usar loops
- [ ] `### Loop defaults` em configuration
- [ ] `### Nós com guardrails` em custom-node-types

**Tests**: none
**Gate**: none

**Commit**: `docs: add loop template and configuration reference`

---

## Parallel Execution Map

```
Phase 1 (Sequential):
  T1 → T2 → T3 → T4

Phase 2 (Sequential):
  T4 → T5 → T6 → T7

Phase 3 (Parallel, após T1):
  ├── T8 [P]
  ├── T9 [P]
  ├── T10 [P]
  └── T11 [P]

Phase 4 (Sequential, após T7 e Phase 3):
  T12 → T13 → T14 → T15

Phase 5 (P1):
  T5 → T16 [P] → T17 → T18
  T7 + T8 → T19 [P]

Phase 6 (Parallel, após T14):
  ├── T20 [P]
  └── T21 [P]
```

**Parallelism constraint:** T12–T13 usam DB de teste — sem `[P]`. T18 depende de T17 — sequencial.

**Entrega v0.2.0 M1 (P0):** T1–T15. **P1:** T16–T19. **Docs:** T20–T21.

---

## Task Granularity Check

| Task | Scope | Status |
|------|-------|--------|
| T1: Config loop | 1 arquivo config | ✅ Granular |
| T2: MaxLoopIterationsException | 1 classe | ✅ Granular |
| T3: CycleDetector | 1 classe + 1 teste | ✅ Granular |
| T4: GraphValidator ciclos | 1 classe modify + testes | ✅ Granular |
| T5: LoopNodeExecutor | 1 executor + 1 teste | ✅ Granular |
| T6: ServiceProvider wiring | 1 arquivo modify | ✅ Granular |
| T7: GraphExecutionLoop | 1 classe modify + 1 teste | ✅ Granular |
| T8: WorkflowNode handles | 1 componente + CSS | ✅ Granular |
| T9: graph.js edge styles | 1–2 arquivos JS | ✅ Granular |
| T10: NodeConfigForm loop | 1 seção inspector | ✅ Granular |
| T11: nodeUtils defaults | 1 função/bloco | ✅ Granular |
| T12: WorkflowLoopTest (core) | 1 arquivo teste (casos core) | ✅ Granular |
| T13: WorkflowLoopTest (HITL) | casos adicionais mesmo arquivo | ✅ Granular |
| T14: Template JSON + testes | 1 template + asserts | ✅ Granular |
| T15: Gate final P0 | suite completa | ✅ Granular |
| T16: LoopNodeCodeGenerator | 1 generator + registry | ✅ Granular |
| T17: GraphTranspiler loop | 1 classe modify | ✅ Granular |
| T18: Exporter test loop | testes adicionais | ✅ Granular |
| T19: Harness iteration UI | 1–2 componentes | ✅ Granular |
| T20–T21: Docs | 3–4 arquivos cada | ✅ Granular (coeso por tema) |

---

## Diagram-Definition Cross-Check

| Task | Depends On (body) | Diagram Shows | Status |
|------|-------------------|---------------|--------|
| T1 | None | Phase 1 start | ✅ |
| T2 | None | Phase 1 (paralelo conceitual a T1) | ✅ |
| T3 | T1 | T1 → T3 | ✅ |
| T4 | T3 | T3 → T4 | ✅ |
| T5 | T1, T2 | T4 → T5 | ✅ |
| T6 | T5 | T5 → T6 | ✅ |
| T7 | T5, T6 | T6 → T7 | ✅ |
| T8 | T1 | T1 → T8 [P] | ✅ |
| T9 | T1 | T1 → T9 [P] | ✅ |
| T10 | T1 | T1 → T10 [P] | ✅ |
| T11 | T1 | T1 → T11 [P] | ✅ |
| T12 | T4, T5, T7 | T7 → T12 | ✅ |
| T13 | T12 | T12 → T13 | ✅ |
| T14 | T4, T8 | T13 → T14 → T15 | ✅ |
| T15 | T14 | T14 → T15 | ✅ |
| T16 | T5 | T5 → T16 [P] | ✅ |
| T17 | T16 | T16 → T17 | ✅ |
| T18 | T14, T17 | T17 → T18 | ✅ |
| T19 | T7, T8 | T7+T8 → T19 [P] | ✅ |
| T20 | T14 | T14 → T20 [P] | ✅ |
| T21 | T14 | T14 → T21 [P] | ✅ |

---

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
|------|----------------------------|-----------------|-----------|--------|
| T1 | config | none | none | ✅ OK |
| T2 | exception class | unit (via T5) | unit (co-localizado T5) | ✅ OK |
| T3 | CycleDetector | unit | unit | ✅ OK |
| T4 | GraphValidator | unit | unit | ✅ OK |
| T5 | LoopNodeExecutor | unit | unit | ✅ OK |
| T6 | ServiceProvider wiring | none | none | ✅ OK |
| T7 | GraphExecutionLoop | unit | unit | ✅ OK |
| T8–T11 | canvas JS | none (build) | none | ✅ OK |
| T12 | WorkflowRunner loop | integration | integration | ✅ OK |
| T13 | WorkflowRunner resume | integration | integration | ✅ OK |
| T14 | template + installer | integration | integration | ✅ OK |
| T15 | full suite | integration | integration | ✅ OK |
| T16 | LoopNodeCodeGenerator | unit (exporter) | unit | ✅ OK |
| T17 | GraphTranspiler | unit (exporter) | unit | ✅ OK |
| T18 | export pipeline | integration | integration | ✅ OK |
| T19 | harness UI | none (build) | none | ✅ OK |
| T20–T21 | docs | none | none | ✅ OK |

---

## Rastreabilidade LOOP-XX

| Requirement | Tasks |
|-------------|-------|
| LOOP-01 | T1, T6, T8, T9 |
| LOOP-02 | T1, T5, T10, T11 |
| LOOP-03 | T2, T5, T7, T12 |
| LOOP-04 | T3, T4 |
| LOOP-05 | T3, T4 |
| LOOP-06 | T5, T7 |
| LOOP-07 | T14 |
| LOOP-08 | T16, T17, T18 |
| LOOP-09 | T19 |
| LOOP-10 | T4, T5, T7, T12, T13, T14, T15, T18 |

---

## Gaps design vs codebase (referência)

| Design propõe | Codebase atual | Decisão nas tasks |
|---------------|----------------|-------------------|
| `LoopNode.jsx` dedicado | Todos os nós em `WorkflowNode.jsx` | Estender `WorkflowNode.jsx` (T8) |
| `LoopInspector.jsx` | Inspector em `NodeConfigForm.jsx` | Seção loop em `NodeConfigForm.jsx` (T10) |
| `graphValidation.js` client-side | Validação via Livewire `validateGraphPayload` | Reutilizar `graphJson.js` + `GraphValidator` (T4) |
| `nodePalette.js` | Palette em `WorkflowEditorShell.jsx` + config | Auto-descoberta via `node_types.loop` (T1) |
| `loop.global_max_steps` | Não existe ainda | Opcional em T1; guardrail em T7 |
| `CycleDetector.php` | Não existe | T3 |
| `GraphTranspiler` loop events | Só `condition` tem branches | T17 |
