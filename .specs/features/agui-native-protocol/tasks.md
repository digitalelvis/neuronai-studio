# AG-UI Native Protocol — Tasks

Traceability: `AGUI-01`…`AGUI-09` in [spec.md](spec.md).  
**Feature:** `agui-native-protocol` (M17) · **Line:** `v3.1.x`  
**Commit scope:** `feat(integration)`

## AGUI-T1 — Parser + JsonPatch + ClientFacingState + AguiProtocol

- **What:** Add `RunAgentInputParser`, `JsonPatch`, `ClientFacingState`, `AguiProtocol`.
- **Where:** `src/Integration/{RunAgentInputParser,JsonPatch,ClientFacingState,AguiProtocol}.php`
- **Depends on:** —
- **Done when:** Parser maps RunAgentInput and fallback; cancelled resume 422; JsonPatch diffs nested maps; AguiProtocol emits SSE JSON.
- **Tests:** `RunAgentInputParserTest`, `JsonPatchTest`
- **Requirements:** AGUI-01, AGUI-02, AGUI-04, AGUI-07

## AGUI-T2 — Registry runId

- **What:** `StreamAdapterRegistry::resolve($protocol, $threadId = null, $runId = null)` passes runId into `AGUIAdapter`.
- **Where:** `src/Integration/StreamAdapterRegistry.php`
- **Depends on:** —
- **Done when:** `resolve('agui', 't1', 'r1')` echoes those ids on `start()`.
- **Tests:** `StreamAdapterRegistryTest`
- **Requirements:** AGUI-03

## AGUI-T3 — Agent integrate RunAgentInput + snapshots

- **What:** Agent AG-UI controller uses parser, passes runId, injects `MESSAGES_SNAPSHOT` + empty `STATE_SNAPSHOT` after `RUN_STARTED`. Vercel path unchanged.
- **Where:** `src/Http/Controllers/Integration/AgentIntegrateStreamController.php`, `src/Services/ChatThreadLoader.php` (message `id`)
- **Depends on:** T1, T2
- **Done when:** CopilotKit body streams; `{ message }` fallback; threadId/runId echoed; snapshot present.
- **Tests:** `AgentIntegrateStreamTest`
- **Requirements:** AGUI-01…06

## AGUI-T4 — step_completed carries state

- **What:** Include `state => $state->all()` on interpreted + native `step_completed` emits.
- **Where:** `src/Runtime/BuilderWorkflowState.php`, `src/Runtime/StudioTraceMiddleware.php`
- **Depends on:** —
- **Done when:** Bridge can read state from `step_completed` payload.
- **Tests:** covered by T5
- **Requirements:** AGUI-07

## AGUI-T5 — Workflow bridge snapshots, delta, interrupt

- **What:** AG-UI start snapshots; STATE_DELTA on step_completed; dual-emit CUSTOM + interrupt `RUN_FINISHED`; skip vanilla `end()` on pause.
- **Where:** `src/Integration/WorkflowStreamBridge.php`
- **Depends on:** T1, T4
- **Done when:** Human AG-UI stream has snapshots + CUSTOM + `outcome.interrupt` with `interruptId` = run id.
- **Tests:** `WorkflowIntegrateStreamTest`
- **Requirements:** AGUI-06, AGUI-07, AGUI-08

## AGUI-T6 — Workflow stream parse + resume[]

- **What:** Workflow AG-UI controller parses RunAgentInput; `resume[]` looks up run and calls `WorkflowRunner::resume` via bridge. Fallback `{ message }` kept.
- **Where:** `src/Http/Controllers/Integration/WorkflowIntegrateStreamController.php`
- **Depends on:** T1, T2, T5
- **Done when:** RunAgentInput starts a workflow; `resume[]` completes a paused Human run.
- **Tests:** `WorkflowIntegrateStreamTest`, `WorkflowIntegrateResumeTest` (stream resume case)
- **Requirements:** AGUI-01…04, AGUI-08

## AGUI-T7 — Legacy resume URL echoes client thread

- **What:** Resume controller adapter uses public thread id + optional body runId, not `$run->id` as threadId.
- **Where:** `src/Http/Controllers/Integration/WorkflowIntegrateResumeController.php`
- **Depends on:** T2
- **Done when:** M4 `{ message }` resume still completes; `RUN_STARTED.threadId` is the conversation thread.
- **Tests:** `WorkflowIntegrateResumeTest`
- **Requirements:** AGUI-03, AGUI-08

## AGUI-T8 — Docs + Connect Panel

- **What:** Update `docs/guides/integration/ag-ui.md` and AG-UI Connect Panel snippet (`HttpAgent` / `RunAgentInput`). Vercel snippet unchanged.
- **Where:** `docs/guides/integration/ag-ui.md`, `resources/js/components/ConnectPanel.jsx`
- **Depends on:** T3, T6
- **Done when:** Docs describe both bodies, snapshots, dual-emit HITL, both resume paths.
- **Tests:** none (docs); Connect Panel source review
- **Requirements:** AGUI-05, AGUI-09
