# Quickstart: First Workflow

Run a pre-built workflow template, inspect the execution trace, and understand the studio runtime.

## What you will build

A single-agent chat workflow using the `basic-agent-chat` template — the simplest end-to-end workflow in the studio.

## Prerequisites

- [Installation](installation.md) completed
- At least one agent exists (or use a template that creates one automatically)

## Step 1 — Browse templates

Navigate to **Templates**:

```
/neuronai-studio/templates
```

Filter by **Workflows** and find **Basic Agent Chat**. Click **Use Template**.

The installer creates any required agents, remaps graph references, and opens the workflow editor.

## Step 2 — Inspect the graph

The canvas shows a minimal flow:

```mermaid
flowchart LR
    Start[Start] --> Agent[Agent] --> Stop[Stop]
```

Click the **Agent** node to see its configuration: which agent runs, the message template (`{{input}}`), and the output state key.

## Step 3 — Run the test harness

Open the **Test** panel (workflow chat harness). Type a message and send it.

The runtime:

1. Sets `input` in workflow state from your message
2. Executes nodes in graph order
3. Streams step events and tokens via SSE
4. Persists a trace record

## Step 4 — Inspect the trace

After the run completes, open **Traces** for this workflow. Click the latest trace to see:

- Per-step timeline (start → agent → stop)
- Input and output payloads for each step
- Total duration and any errors

## Next steps

- [Workflow Overview](../guides/workflows/overview.md) — concepts and node types
- [Canvas Editor](../guides/workflows/canvas-editor.md) — build custom graphs
- [State & Conditions](../guides/workflows/state-and-conditions.md) — branch on workflow data
- [Templates](../guides/templates.md) — try `lead-qualification` or `support-rag-hitl`
