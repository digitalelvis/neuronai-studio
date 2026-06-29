# Runtime & Traces

Execute workflows from the test harness with server-sent events (SSE), persisted trace records, and step-by-step inspection.

## Running a workflow

Open a workflow editor and use the **Test** panel (workflow chat harness). Each run:

1. Sets `input` in state from your message
2. Merges optional "Initial state JSON"
3. Executes nodes via `GraphExecutionLoop`
4. Streams events to the browser
5. Persists a trace record

<!-- SCREENSHOT: workflows-test-harness -->
> **Screenshot pending:** Test harness running a workflow.
>
> Asset path: `docs/assets/screenshots/workflows-test-harness.png`
> Capture: Workflow editor test panel with active run — dark theme, 1440×900

![Workflow test harness](../../assets/screenshots/workflows-test-harness.png)

## Streaming architecture

```mermaid
sequenceDiagram
    participant UI as TestHarness
    participant API as WorkflowStreamController
    participant Runner as WorkflowRunner
    participant Loop as GraphExecutionLoop
    participant Trace as WorkflowTrace

    UI->>API: POST /workflows/{id}/run/stream
    API->>Runner: execute(workflow, state)
    Runner->>Loop: next node
    Loop-->>API: step/token events SSE
    API-->>UI: stream events
    Loop->>Trace: persist step
    Loop->>Loop: until stop or HITL
```

### SSE event types

| Event | Description |
|-------|-------------|
| `step_start` | Node execution begins |
| `step_complete` | Node finished with output |
| `token` | Streaming text from agent/LLM nodes |
| `error` | Execution failure |
| `human_required` | Workflow paused at Human node |
| `done` | Run complete |

## Trace records

Every run creates a `WorkflowTrace` with associated `WorkflowTraceStep` records.

<!-- SCREENSHOT: workflows-traces-list -->
> **Screenshot pending:** Trace list for a workflow.
>
> Asset path: `docs/assets/screenshots/workflows-traces-list.png`
> Capture: `/neuronai-studio/workflows/{id}/traces` — dark theme, 1440×900

![Workflow traces list](../../assets/screenshots/workflows-traces-list.png)

### Trace list

```
/neuronai-studio/workflows/{id}/traces
```

Shows run status, duration, and timestamps.

### Trace detail

```
/neuronai-studio/traces/{id}
```

<!-- SCREENSHOT: workflows-trace-detail -->
> **Screenshot pending:** Step timeline with input/output expanded.
>
> Asset path: `docs/assets/screenshots/workflows-trace-detail.png`
> Capture: Trace detail page — dark theme, 1440×900

![Trace detail](../../assets/screenshots/workflows-trace-detail.png)

Each step shows:

- Node type and ID
- Input state snapshot
- Output / error
- Duration

Export trace JSON:

```
/neuronai-studio/traces/{id}/json
```

## Initial state JSON

Pass structured context at run start:

```json
{
  "tier": "gold",
  "customer_id": "12345"
}
```

Reference keys in node templates with `{{tier}}`, `{{customer_id}}`, etc.

## Related code

- `WorkflowRunner`, `GraphExecutionLoop`, `GraphInterpreterWorkflow`
- `WorkflowTrace`, `WorkflowTraceStep` models
- `WorkflowStreamController`, `WorkflowTraceController`

## See also

- [Human-in-the-Loop](human-in-the-loop.md)
- [State & Conditions](state-and-conditions.md)
