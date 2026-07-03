# Execução Paralela em Workflows — Tasks

Traceability: cada task referencia os IDs `PE-01..PE-09` da [spec](spec.md) e o [design](design.md).

## Tasks

### PE-T1 — Node types fork/join + validação (PE-01, PE-04)

- **What:** Registrar tipos `fork`/`join` (config + executors + node type registry); `GraphValidator` valida pareamento fork→join e branches que alcançam o join.
- **Where:** `config/neuronai-studio.php`, `src/NeuronAIStudioServiceProvider.php`, `src/Runtime/GraphValidator.php`.
- **Done when:** grafo com fork sem join (ou branch que não alcança join) é rejeitado; fork/join válidos passam.
- **Tests:** `GraphValidatorTest`, `WorkflowParallelExecutionTest`.
- **Requirements:** PE-01, PE-04.

### PE-T2 — Runtime interpretado fork/join (PE-02, PE-03)

- **What:** `ForkNodeExecutor` roda branches (via `ParallelBranchRunner`) em estado isolado até o join; `JoinNodeExecutor` agrega resultados em `output_key`.
- **Where:** `src/Runtime/ParallelBranchRunner.php`, `src/Runtime/NodeExecutors/ForkNodeExecutor.php`, `src/Runtime/NodeExecutors/JoinNodeExecutor.php`, `src/Runtime/GraphExecutionLoop.php` (parâmetro `stopAt`).
- **Done when:** fork com 2 branches converge no join com objeto `{ branch_a, branch_b }`; SSE `branch_started`/`branch_completed`.
- **Tests:** `WorkflowParallelExecutionTest`.
- **Requirements:** PE-02, PE-03.

### PE-T3 — Branch interrupt + resume parcial (PE-05, PE-06)

- **What:** `ParallelBranchInterruptException` carrega contexto parallel; `WorkflowRunner` pausa (SSE `parallel_interrupt`) e retoma apenas a branch pendente reusando resultados completos.
- **Where:** `src/Runtime/Exceptions/ParallelBranchInterruptException.php`, `src/Runtime/NodeExecutors/ForkNodeExecutor.php`, `src/Runtime/WorkflowRunner.php`.
- **Done when:** Human node numa branch pausa; resume completa só a branch pendente sem re-executar branches concluídas.
- **Tests:** `WorkflowParallelExecutionTest`.
- **Requirements:** PE-05, PE-06.

### PE-T4 — Codegen ParallelEvent (PE-07)

- **What:** `ForkNodeCodeGenerator`/`JoinNodeCodeGenerator` + `GraphTranspiler` emitem uma subclasse `ParallelEvent`; fork retorna `new XParallelEvent([...])`, join consome via `getResult()`.
- **Where:** `src/Codegen/GraphTranspiler.php`, `src/Codegen/NativeWorkflowExporter.php`, `src/Codegen/Stubs/native-parallel-event.stub`, `src/Codegen/NodeCodeGenerators/{Fork,Join}NodeCodeGenerator.php`, registry + `CodegenContext`.
- **Done when:** preview exporta uma classe `extends ParallelEvent` e o fork a instancia; join lê `getResult`.
- **Tests:** `NativeWorkflowExporterTest`.
- **Requirements:** PE-07.

### PE-T5 — Canvas + inspector (PE-01, PE-08)

- **What:** Node types fork/join no palette; fork renderiza um handle por branch nomeada; inspector com editor de branches (fork) e `output_key` (join).
- **Where:** `config/neuronai-studio.php` (node_types), `resources/js/studio-canvas/nodes/WorkflowNode.jsx`, `resources/js/studio-canvas/inspector/NodeConfigForm.jsx`, bundle `resources/js/dist/workflow-canvas.bundle.js`.
- **Done when:** fork/join aparecem no canvas; fork lista/edita branches (cada uma vira handle nomeado); join edita `output_key`; bundle reconstruído.
- **Requirements:** PE-01, PE-08 (parcial — preview agregado no inspector fica de fora).

## Deferred / Partial

- **PE-08 (preview agregado no inspector):** o inspector do join edita `output_key` mas não renderiza um preview dos resultados agregados (esses aparecem no trace/SSE). Fica como follow-up.
- **Tool approval dentro de branch:** o interrupt paralelo captura apenas Human nodes; aprovação de tool dentro de uma branch não é dividida por branch (usar Human gate como workaround).
- **Runtime nativo paralelo:** execução usa runtime interpretado (branches sequenciais, estado isolado). O codegen nativo emite `ParallelEvent` válido para export, mas a orquestração concorrente via `AsyncExecutor` do Neuron não é exercida em runtime pelo Studio (ver AD).
