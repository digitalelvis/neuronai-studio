# Workflow Code Bridge — Design

## StudioWorkflow contract

```php
interface StudioWorkflow
{
    /** @return array{name?: string, description?: string, status?: string} */
    public static function studioMeta(): array;

    /** @return array{version: int, nodes: array, edges: array, viewport?: array} */
    public static function studioGraph(): array;
}
```

## Discovery

`WorkflowRegistry` mirrors `ToolRegistry`:

- Scan `workflow_scan_paths` for classes implementing `StudioWorkflow`
- Scan `workflow_json_paths` for `.json` files (graph or `{ meta, graph }` envelope)
- Entries keyed by `class:{FQCN}` or `json:{path}`

## Shadow record (preview + test)

On preview mount (`?class=` or `?json=`):

1. Import graph + meta from source
2. `WorkflowDefinition::updateOrCreate(['class_path' => $ref], [...])` with `source=code`, `locked=true`
3. Editor loads shadow record; `readOnly=true`
4. Re-sync graph from source on every mount

Test harness uses existing `POST /workflows/{workflow}/run/stream` against shadow ID.

## Export round-trip

`WorkflowExporter` emits PHP class implementing `StudioWorkflow` with `var_export()` of graph array — not Neuron native `Workflow` stub.

## Import to Studio

Index action duplicates shadow/source graph into new row: `source=studio`, `locked=false`, `class_path=null`.
