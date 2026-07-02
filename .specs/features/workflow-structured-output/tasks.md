# Structured Output em Workflows — Tasks

**Design**: `.specs/features/workflow-structured-output/design.md`
**Spec**: `.specs/features/workflow-structured-output/spec.md`
**Status**: Done — M2 feature 4 (T12 loop hint parcial; aguarda inspector loop M1)

> **TESTING.md**: inexistente neste repositório. Matriz inferida dos padrões em `tests/`:
>
> | Camada | Tipo de teste | Comando gate | Parallel-Safe |
> |--------|---------------|--------------|---------------|
> | `src/Runtime/*`, `src/Registry/*` | unit | `vendor/bin/phpunit --filter {Class}Test` | Sim |
> | `WorkflowRunner` / templates | integration | `vendor/bin/phpunit --filter {Class}Test` | Não (DB) |
> | Canvas JS | none (build) | `BUILD_TARGET=canvas npm run build` | Sim |
> | `docs/` | none | revisão manual | Sim |

---

## Execution Plan

### Phase 1: Registry & config (Sequential)

```
T1 → T2 → T3
```

### Phase 2: State resolution (Sequential)

Dot notation compartilhada entre condition e loop.

```
T3 ──→ T4 → T5
```

### Phase 3: Backend executors (Sequential)

Structured branch nos executors + AgentRunner.

```
T5 ──→ T6 → T7 → T8 → T9
```

### Phase 4: Canvas API + UI (Parallel OK após T2)

```
T2 ──┬→ T10 [P]
     ├→ T11 [P]
     └→ T12 [P]
T10..T12 ──→ T13
```

### Phase 5: Codegen P1 (Parallel OK)

```
T7 + T8 ──┬→ T14 [P]
          └→ T15 [P]
```

### Phase 6: Integration + docs (Sequential)

```
T9 + T13 ──→ T16 → T17
```

---

## Task Breakdown

### T1: Config `structured_output_scan_paths`

**What**: Registrar paths para scan de output classes PHP no host app.
**Where**: `config/neuronai-studio.php`
**Depends on**: None
**Reuses**: Padrão de `export_path` / `export_namespace`
**Requirement**: SO-02 (base para registry)

**Done when**:

- [ ] Chave `structured_output_scan_paths` como array de paths absolutos/relativos
- [ ] Default inclui `{export_path}/Output` quando diretório existir
- [ ] Gate: `php -r "require 'vendor/autoload.php'; var_export(config('neuronai-studio.structured_output_scan_paths'));"`

**Tests**: none
**Gate**: quick

**Commit**: `feat(workflows): add structured_output_scan_paths config`

---

### T2: `OutputClassRegistry`

**What**: Scan de classes PHP com `SchemaProperty` nos paths configurados; retorna lista `{ class, label, properties? }`.
**Where**: `src/Registry/OutputClassRegistry.php`
**Depends on**: T1
**Reuses**: Padrão de `ToolRegistry` / reflection em export path
**Requirement**: SO-02

**Done when**:

- [ ] `all(): array` retorna classes descobertas com FQCN
- [ ] Ignora classes abstratas / sem atributos `SchemaProperty`
- [ ] Registrado no service provider
- [ ] Gate: `vendor/bin/phpunit --filter OutputClassRegistryTest`

**Tests**: unit (`tests/OutputClassRegistryTest.php` com fixture class em `tests/fixtures/`)
**Gate**: quick

**Commit**: `feat(workflows): add OutputClassRegistry for structured output classes`

---

### T3: `StructuredOutputResolver`

**What**: Resolve FQCN ou short name para classe válida de structured output.
**Where**: `src/Runtime/StructuredOutput/StructuredOutputResolver.php`
**Depends on**: T2
**Reuses**: `OutputClassRegistry`
**Requirement**: SO-03

**Done when**:

- [ ] `resolve(string $reference): string` retorna FQCN
- [ ] Lança exceção clara se classe inexistente ou inválida
- [ ] Gate: `vendor/bin/phpunit --filter StructuredOutputResolverTest`

**Tests**: unit
**Gate**: quick

**Commit**: `feat(workflows): add StructuredOutputResolver`

---

### T4: `WorkflowStateValue` helper (dot notation)

**What**: Utilitário `get(WorkflowState $state, string $key): mixed` usando `data_get` sobre `$state->all()`.
**Where**: `src/Runtime/WorkflowStateValue.php`
**Depends on**: None
**Reuses**: Laravel `data_get`
**Requirement**: SO-05

**Done when**:

- [ ] `lead.email` resolve nested arrays no state
- [ ] Chaves simples (`tier`) continuam funcionando
- [ ] Gate: `vendor/bin/phpunit --filter WorkflowStateValueTest`

**Tests**: unit
**Gate**: quick

**Commit**: `feat(workflows): add WorkflowStateValue dot-notation helper`

---

### T5: Dot notation em `ConditionNodeExecutor` e `LoopNodeExecutor`

**What**: Substituir `$state->get($key)` por `WorkflowStateValue::get`.
**Where**: `src/Runtime/NodeExecutors/ConditionNodeExecutor.php`, `LoopNodeExecutor.php`
**Depends on**: T4
**Reuses**: T4
**Requirement**: SO-05

**Done when**:

- [ ] Condition com `state_key: lead.tier` + `equals: gold` roteia corretamente
- [ ] Loop exit condition com dot notation funciona
- [ ] Testes existentes em `ConditionNodeExecutorTest` passam
- [ ] Novos casos dot notation cobertos
- [ ] Gate: `vendor/bin/phpunit --filter ConditionNodeExecutorTest`

**Tests**: unit (estender `ConditionNodeExecutorTest`)
**Gate**: quick

**Commit**: `feat(workflows): support dot notation in condition and loop nodes`

---

### T6: `AgentRunner::structuredInline`

**What**: Método que chama `$agent->structured($message, $class)` e retorna DTO/array + eventos de tool.
**Where**: `src/Runtime/AgentRunner.php`
**Depends on**: T3
**Reuses**: `makeAgent`, `AgentRunResult` (estender se necessário)
**Requirement**: SO-03, SO-04

**Done when**:

- [ ] Aceita `UserMessage`, thread key, config inline ou `AgentDefinition`
- [ ] Retorna objeto serializável (`toArray()` ou array nativo)
- [ ] Gate: `vendor/bin/phpunit --filter AgentRunnerStructuredTest`

**Tests**: unit com `FakeAIProvider` + fixture output class
**Gate**: quick

**Commit**: `feat(agents): add AgentRunner structuredInline for workflow nodes`

---

### T7: Branch structured em `LlmNodeExecutor`

**What**: Quando `data.structured === true`, usar `Agent::make()->structured()` com output class resolvida (não `provider->chat()`).
**Where**: `src/Runtime/NodeExecutors/LlmNodeExecutor.php`
**Depends on**: T3, T6 (padrão structured)
**Reuses**: `MessageFactory`, `StateTemplateInterpolator`
**Requirement**: SO-03, SO-04

**Done when**:

- [ ] `structured: false` ou ausente = comportamento atual (string em `output_key`)
- [ ] `structured: true` + `output_class` grava array/objeto validado em `output_key`
- [ ] Gate: `vendor/bin/phpunit --filter LlmNodeExecutorTest`

**Tests**: unit (estender `LlmNodeExecutorTest`)
**Gate**: quick

**Commit**: `feat(workflows): structured output mode for LLM nodes`

---

### T8: Branch structured em `AgentNodeExecutor`

**What**: Mesmo padrão de T7 para nós agent (inline e `agent_id`).
**Where**: `src/Runtime/NodeExecutors/AgentNodeExecutor.php`
**Depends on**: T6, T7
**Reuses**: `AgentRunner::structuredInline`
**Requirement**: SO-03, SO-04

**Done when**:

- [ ] Agent com `structured: true` grava objeto tipado em `output_key`
- [ ] Tools/memory preservados quando structured off
- [ ] Gate: `vendor/bin/phpunit --filter AgentNodeExecutorTest`

**Tests**: unit (estender `AgentNodeExecutorTest`)
**Gate**: quick

**Commit**: `feat(workflows): structured output mode for agent nodes`

---

### T9: Erros de validação → trace step failed

**What**: Capturar exceções de validação Neuron (`ValidationException` ou equivalente) e marcar step com `validation_errors` no SSE/trace.
**Where**: `src/Runtime/Exceptions/StructuredOutputValidationException.php`, `GraphExecutionLoop` ou executors, `WorkflowRunner`
**Depends on**: T7, T8
**Reuses**: Padrão `WorkflowExecutionException` / step metadata
**Requirement**: SO-06

**Done when**:

- [ ] Resposta LLM inválida → trace `failed` com mensagens de validação
- [ ] `step_completed` ou `trace_failed` inclui `validation_errors` quando aplicável
- [ ] Gate: teste dedicado em T16

**Tests**: integration (T16)
**Gate**: full

**Commit**: `feat(workflows): surface structured output validation errors in traces`

---

### T10: Expor output classes ao canvas

**What**: Passar `outputClasses` no `__NEURONAI_CANVAS_CONFIG` do editor.
**Where**: `src/Http/Livewire/Workflows/Editor.php`, `resources/views/livewire/workflows/editor.blade.php`
**Depends on**: T2
**Reuses**: Padrão `toolsForCanvas`, `agentsForCanvas`
**Requirement**: SO-02

**Done when**:

- [ ] Blade inclui `outputClasses: @json($outputClassesForCanvas)`
- [ ] Lista `{ class, label }` disponível no inspector
- [ ] Gate: smoke manual ou teste Livewire se existir padrão

**Tests**: none
**Gate**: quick

**Commit**: `feat(studio): expose output classes to workflow canvas config`

---

### T11: `StructuredOutputFields.jsx`

**What**: Toggle `structured` + select `output_class` + hint de schema (P1: preview básico).
**Where**: `resources/js/studio-canvas/inspector/shared/StructuredOutputFields.jsx`
**Depends on**: None (UI only)
**Reuses**: `ProviderModelFields` patterns, shadcn `Switch`/`Select`
**Requirement**: SO-01, SO-02, SO-08 (P1 básico)

**Done when**:

- [ ] Toggle desliga structured e limpa `output_class`
- [ ] Select lista `outputClasses` do config
- [ ] Gate: `BUILD_TARGET=canvas npm run build`

**Tests**: none
**Gate**: quick

**Commit**: `feat(canvas): add StructuredOutputFields inspector component`

---

### T12: Hint dot notation no inspector condition/loop

**What**: Texto de ajuda em `state_key` mencionando dot notation (`lead.email`).
**Where**: `resources/js/studio-canvas/inspector/NodeConfigForm.jsx`
**Depends on**: None
**Reuses**: Hints existentes em output_key
**Requirement**: SO-05 (UX)

**Done when**:

- [ ] Condition e loop mostram exemplo `lead.tier`
- [ ] Gate: `BUILD_TARGET=canvas npm run build`

**Tests**: none
**Gate**: quick

**Commit**: `docs(canvas): hint dot notation for condition state_key`

---

### T13: Integrar structured fields em LLM e Agent inspectors

**What**: Montar `StructuredOutputFields` nos blocos `llm` e `agent` de `NodeConfigForm`.
**Where**: `resources/js/studio-canvas/inspector/NodeConfigForm.jsx`, `InspectorPanel.jsx` (pass props)
**Depends on**: T10, T11
**Reuses**: T11
**Requirement**: SO-01, SO-02

**Done when**:

- [ ] Nó LLM e Agent persistem `structured` + `output_class` no graph JSON
- [ ] `output_key` visível também no bloco agent (paridade com LLM)
- [ ] Gate: `BUILD_TARGET=canvas npm run build`

**Tests**: none
**Gate**: quick

**Commit**: `feat(canvas): structured output toggles on LLM and agent nodes`

---

### T14: Codegen structured — `LlmNodeCodeGenerator`

**What**: Emitir `->structured(OutputClass::class)` quando `structured: true`.
**Where**: `src/Codegen/NodeCodeGenerators/LlmNodeCodeGenerator.php`
**Depends on**: T7
**Reuses**: `CodegenContext` imports
**Requirement**: SO-07 (P1)

**Done when**:

- [ ] Export inclui import da output class
- [ ] Gate: `vendor/bin/phpunit --filter NativeWorkflowExporterTest`

**Tests**: integration (estender exporter test)
**Gate**: quick

**Commit**: `feat(codegen): emit structured() for LLM nodes`

---

### T15: Codegen structured — `AgentNodeCodeGenerator`

**What**: Emitir `structuredInline` ou `->structured()` no export de agent nodes.
**Where**: `src/Codegen/NodeCodeGenerators/AgentNodeCodeGenerator.php`
**Depends on**: T8
**Reuses**: T14 pattern
**Requirement**: SO-07 (P1)

**Done when**:

- [ ] Export agent com structured inclui output class
- [ ] Gate: `vendor/bin/phpunit --filter NativeWorkflowExporterTest`

**Tests**: integration
**Gate**: quick

**Commit**: `feat(codegen): emit structured output for agent nodes`

---

### T16: Teste integração round-trip (SO-09)

**What**: Workflow fake: LLM structured → condition em campo nested → branch correto.
**Where**: `tests/StructuredOutputWorkflowTest.php`, fixture `LeadProfile` em `tests/fixtures/`
**Depends on**: T5, T7, T9
**Reuses**: `ConditionNodeExecutorTest::conditionBranchGraph` pattern
**Requirement**: SO-09

**Done when**:

- [ ] Grafo LLM (`structured`) → condition (`lead.tier` equals `gold`) → set_state branch
- [ ] Caso negativo: validação falha → trace failed
- [ ] Gate: `vendor/bin/phpunit --filter StructuredOutputWorkflowTest`

**Tests**: integration
**Gate**: full

**Commit**: `test(workflows): structured output round-trip with condition routing`

---

### T17: Documentação

**What**: Atualizar docs listados na spec.
**Where**: `docs/guides/workflows/node-types/ai-nodes.md`, `state-and-conditions.md`, `guides/agents/creating-agents.md`, `reference/configuration.md`, `extending/custom-node-types.md`
**Depends on**: T16
**Reuses**: Estilo docs AMA / cyclic graphs
**Requirement**: spec Documentation table

**Done when**:

- [ ] Seções Structured output + dot notation documentadas
- [ ] `structured_output_scan_paths` em configuration reference

**Tests**: none
**Gate**: manual review

**Commit**: `docs(workflows): structured output and dot notation in conditions`

---

## Traceability

| Requirement | Tasks |
|-------------|-------|
| SO-01 | T11, T13 |
| SO-02 | T1, T2, T10, T13 |
| SO-03 | T3, T6, T7, T8 |
| SO-04 | T7, T8 |
| SO-05 | T4, T5, T12 |
| SO-06 | T9, T16 |
| SO-07 | T14, T15 |
| SO-08 | T11 (P1 preview) |
| SO-09 | T16 |
