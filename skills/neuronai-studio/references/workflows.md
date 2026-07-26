# Workflows

## What

Visual graphs in `workflow_definitions` (nodes + edges JSON). Shared **state**, persisted **traces**. Runner: `WorkflowRunner` → node executors.

## Node categories

| Category | Types |
|----------|-------|
| Flow | start, stop, delay, human |
| AI | agent, llm, tool, mcp, rag |
| Logic | condition, set_state, loop, fork, join |

Cycles need a **loop** node with `max_steps`. Parallel work uses **fork/join**.

## Workflow

1. Create workflow or install template
2. Edit canvas; wire handles; configure node data
3. Run test harness; inspect traces
4. HITL: **human** node pauses until resume
5. Export PHP when ready — [export.md](export.md)

## Checklist

- [ ] Graph has start → … → stop
- [ ] Conditions/loops reference real state keys
- [ ] Long runs: prefer async queue (`NEURONAI_STUDIO_ASYNC_RUNS_ENABLED`) under PHP-FPM
- [ ] Tool Mode / toolable nodes only when product supports exposure contract

## Documentation (canonical)

- [Overview](../../../docs/guides/workflows/overview.md)
- [Canvas Editor](../../../docs/guides/workflows/canvas-editor.md)
- [State & Conditions](../../../docs/guides/workflows/state-and-conditions.md)
- [Flow Nodes](../../../docs/guides/workflows/node-types/flow-nodes.md)
- [AI Nodes](../../../docs/guides/workflows/node-types/ai-nodes.md)
- [Logic Nodes](../../../docs/guides/workflows/node-types/logic-nodes.md)
- [Runtime & Traces](../../../docs/guides/workflows/runtime-and-traces.md)
- [Human-in-the-Loop](../../../docs/guides/workflows/human-in-the-loop.md)
- [Templates](../../../docs/guides/templates.md)
