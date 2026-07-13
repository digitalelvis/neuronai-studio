# Checkpoints e Persistência em Workflows — Tasks

Traceability: cada task referencia os IDs `CP-01..CP-08` da [spec](spec.md) e o [design](design.md).

## Tasks

### CP-T1 — Migration + model + config (CP-05)

- **What:** Tabela `neuronai_studio_workflow_checkpoints` (trace nullable + `workflow_key` p/ native), model `WorkflowCheckpoint`, config `checkpoints.*` (`enabled`, `ttl`).
- **Where:** `database/migrations/2024_01_01_000016_create_workflow_checkpoints_table.php`, `src/Models/WorkflowCheckpoint.php`, `config/neuronai-studio.php`, service provider migration load.
- **Done when:** migration cria tabela com unique `(workflow_trace_id, node_id, iteration)` + index `workflow_key`; model faz cast de `state_payload`; config expõe `checkpoints.enabled` / `checkpoints.ttl`.
- **Tests:** `MigrationTest` (tabela existe), `WorkflowCheckpointTest`.
- **Requirements:** CP-05.

### CP-T2 — CheckpointService (CP-01, CP-06)

- **What:** Serviço que grava/lê checkpoint por `trace_id + node_id + iteration`, compara `input_hash` (invalidação), aplica TTL (`expires_at`) e `purgeExpired()`.
- **Where:** `src/Runtime/Checkpoint/CheckpointService.php`.
- **Done when:** `lookup()` ignora rows expiradas; `store()` faz upsert; chave = `sha256(trace|node|iteration|input_hash)`.
- **Tests:** `CheckpointServiceTest`.
- **Requirements:** CP-01, CP-06.

### CP-T3 — CheckpointingExecutor decorator (CP-02, CP-03)

- **What:** Decorator `NodeExecutorInterface` que envolve executors opt-in (`checkpoint: true`) e pula re-execução em hit; escopo por iteração de loop.
- **Where:** `src/Runtime/NodeExecutors/CheckpointingExecutor.php`, wiring no `NeuronAIStudioServiceProvider` (agent/llm/rag/tool).
- **Done when:** flag off ou config disabled → delega direto ao inner; hit → merge do diff + evento `checkpoint_hit`; miss → executa + grava diff.
- **Tests:** `CheckpointServiceTest` (decorator hit/miss/disable/iteration/invalidation).
- **Requirements:** CP-02, CP-03, CP-07 (evento backend).

### CP-T4 — EloquentPersistence adapter (CP-04)

- **What:** `PersistenceInterface` + `SerializablePersistenceInterface` backed pela tabela `workflow_checkpoints` (`workflow_key`), para native Neuron workflows.
- **Where:** `src/Runtime/Persistence/EloquentPersistence.php`.
- **Done when:** `save/load/delete` persistem/recuperam `WorkflowInterrupt` serializado por `workflow_id`.
- **Tests:** `EloquentPersistenceTest`.
- **Requirements:** CP-04.

### CP-T5 — Purge command + tests (CP-05, CP-08)

- **What:** Comando `neuronai-studio:checkpoints:purge` + suíte de testes CP-08.
- **Where:** `src/Commands/PurgeCheckpointsCommand.php`, `tests/CheckpointServiceTest.php`, `tests/EloquentPersistenceTest.php`.
- **Done when:** primeira run grava checkpoint; resume não chama provider fake 2x; loops scoped por iteration; disable global; suíte verde.
- **Requirements:** CP-05, CP-08.

## Deferred / Partial

- **CP-07 (UI badge):** backend emite evento `checkpoint_hit` + flag `cached` no step; badge React no trace inspector fica deferido (sem rebuild de bundle nesta fatia).
