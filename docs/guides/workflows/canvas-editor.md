# Canvas Editor

The workflow canvas is a React Flow-based visual editor embedded in Livewire. Drag nodes, connect edges, configure forms on the node, and save validated graphs.

## Open the editor

```
/neuronai-studio/workflows/{id}/edit
```

<!-- SCREENSHOT: workflows-canvas -->
> **Screenshot pending:** Full workflow graph with searchable palette, floating Playground/Share, and expanded node forms.
>
> Asset path: `docs/assets/screenshots/workflows-canvas.png`
> Capture: Workflow editor with a multi-node graph — dark theme, 1440×900

![Workflow canvas](../../assets/screenshots/workflows-canvas.png)

## Editor features

| Feature | Description |
|---------|-------------|
| **Node palette** | Searchable, categorized drag-and-drop component list |
| **Inline node forms** | Selected nodes expand with configuration fields on the canvas |
| **Node toolbar** | Controls / Advanced / Collapse / Duplicate / Delete on selection |
| **Playground** | Floating top-right overlay to run and chat with the workflow |
| **Share** | Floating menu for Connect API, PHP export, and JSON |
| **Logs** | Bottom-left drawer for traces, live events, and validation |
| **Sticky notes** | Non-executable annotations stored in `graph.annotations` |
| **Zoom / lock / minimap** | Viewport controls with interactive lock |
| **Undo / redo** | Revert canvas changes |
| **Auto-layout** | Dagre-based graph layout |
| **Edge splicing** | Insert nodes between existing connections |
| **Validate** | Check graph structure before save |
| **Import / export JSON** | Copy graph JSON in/out |

## Architecture

```mermaid
flowchart LR
    Livewire[Workflows/Editor Livewire] --> Shell[WorkflowEditorShell]
    Shell --> Palette[NodePalette]
    Shell --> Canvas[WorkflowCanvas React Flow]
    Shell --> Playground[PlaygroundOverlay]
    Shell --> Share[ShareMenu]
    Shell --> Logs[LogsDrawer]
    Canvas -->|save| Livewire
    Livewire --> DB[(workflow_definitions.graph)]
```

React bundles communicate with Livewire via `window.Livewire` calls. See [Frontend Bundles](../../reference/frontend-bundles.md).

## Save and validate

Before saving, `GraphValidator` checks:

- Exactly one **Start** node
- At least one **Stop** node
- All nodes reachable from Start
- Valid edge connections (handle compatibility)

Sticky notes (`type: note`) are ignored by validation and runtime; they persist under `annotations`.

Fix validation errors in the Logs drawer (Validation tab) before saving.

## JSON graph format

```json
{
  "version": 1,
  "nodes": [
    { "id": "start-1", "type": "start", "position": { "x": 0, "y": 0 }, "data": {} }
  ],
  "edges": [
    { "id": "e1", "source": "start-1", "target": "agent-1", "sourceHandle": "default", "targetHandle": "default" }
  ],
  "annotations": [
    { "id": "note_1", "type": "note", "position": { "x": 40, "y": 40 }, "data": { "text": "Pricing notes" } }
  ],
  "viewport": { "x": 0, "y": 0, "zoom": 1 }
}
```

## Keyboard shortcuts

| Shortcut | Action |
|----------|--------|
| `Delete` / `Backspace` | Remove selected node (not start/stop) |
| `⌘/Ctrl+D` | Duplicate selected node |
| `⌘/Ctrl+Z` | Undo |
| `⌘/Ctrl+Shift+Z` | Redo |
| `Escape` | Clear selection |

## Preview mode

Read-only preview for code-sourced workflows:

```
/neuronai-studio/workflows/preview?class=App\Neuron\Workflows\MyWorkflow
```

## Next steps

- [Node types](node-types/flow-nodes.md)
- [State & Conditions](state-and-conditions.md)
- [Export & Production](../export-and-production.md)
