# Workflows Overview

Workflows are visual graphs that orchestrate multi-step AI processes. Compose nodes on a canvas, run them from a test harness, inspect traces, and export to production PHP classes.

## Core concepts

| Concept | Description |
|---------|-------------|
| **Graph** | Nodes and edges stored as JSON in `workflow_definitions` |
| **State** | Mutable key-value map shared across nodes during a run |
| **Trace** | Persisted execution record with per-step timeline |
| **Node** | A single step (agent call, condition, delay, etc.) |
| **Edge** | Connection between node handles |

```mermaid
flowchart TB
    Editor[Workflow Canvas] --> DB[(workflow_definitions)]
    DB --> Runner[WorkflowRunner]
    Runner --> Loop[GraphExecutionLoop]
    Loop --> Executors[Node Executors]
    Loop --> Trace[(workflow_traces)]
    DB --> Export[WorkflowExporter]
    Export --> PHP[app/Neuron/Workflows]
```

## Node types (12)

| Category | Types |
|----------|-------|
| Flow | start, stop, delay, human |
| AI | agent, llm, tool, mcp, rag |
| Logic | condition, set_state |

See the [node type guides](node-types/flow-nodes.md) for configuration details.

## Studio routes

| Route | Purpose |
|-------|---------|
| `/neuronai-studio/workflows` | List workflows |
| `/neuronai-studio/workflows/create` | Create workflow |
| `/neuronai-studio/workflows/{id}/edit` | Visual editor |
| `/neuronai-studio/workflows/{id}/traces` | Execution history |

## Workflow sources

Workflows can originate from:

| Source | Description |
|--------|-------------|
| Studio UI | Created and edited on the canvas |
| Templates | Installed from JSON templates |
| PHP import | Scanned `StudioWorkflow` classes |
| JSON import | Files in `workflow_json_paths` |

## Typical workflow patterns

| Pattern | Nodes used |
|---------|------------|
| Simple chat | start → agent → stop |
| Branching logic | start → llm → condition → agents → stop |
| Human approval | start → agent → human → agent → stop |
| Tool pipeline | start → tool → llm → stop |

Try the bundled templates — see [Templates](../templates.md).

## Next steps

- [Canvas Editor](canvas-editor.md)
- [State & Conditions](state-and-conditions.md)
- [Runtime & Traces](runtime-and-traces.md)
