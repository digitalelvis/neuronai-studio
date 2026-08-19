# Canvas Node Names Context

**Gathered:** 2026-08-13
**Spec:** `.specs/features/canvas-node-names/spec.md`
**Status:** Ready for Execute (design skipped — inline in tasks)

---

## Feature Boundary

Authors can set a **per-instance display name** (`title`) on executable canvas nodes. The graph `id` never changes. The name is edited in the inspector header (Dify). It is shown on the card, in logs/traces/thread/inspect, and drives native codegen class/event names via a derived slug. Empty header commit does not clear the name.

---

## Implementation Decisions

### Identity (locked)

- **`id` immutable.** Pattern stays `{type}_{Date.now()}` on drop. Edges, `node:{id}` tool bindings, checkpoints, and historical span `name` keep using `id`.
- **`title` additive.** Top-level on the package graph node, **not** inside `data` (type config). Avoids colliding with node-type fields and with React Flow `data.label` (type label).
- **Rejected:** rename-the-id (rewire edges / break in-flight runs / rewrite old traces).
- **Rejected:** display-only (would leave `Agent1734…Node` in export).

### Name rules (locked)

- Free text: spaces and accents allowed.
- Unique per workflow after **trim + case-insensitive** compare.
- Max **80** characters after trim.
- Empty / whitespace on header blur → **revert** (Dify). Do not persist `""`.
- Two different titles that slug to the same identifier → **block save** (validator).
- Uniqueness applies to executable nodes only (not sticky notes).

### Slug (codegen)

```
slug = Str::studly(Str::ascii(trim(title)))
if slug === '' or starts with digit → prefix Str::studly(type)
if still empty → Str::studly(id)
class = slug + 'Node'
event = slug + 'Event'
```

- Untitled node (no `title` key) → **current** `Str::studly(id)` (do not backfill).
- Emoji/punctuation-only title: display unique; codegen falls back to `id`; save allowed.
- `STUDIO_NODE_ID` always the graph `id`.

Client (canvas) enforces **title** uniqueness on commit. **Slug** uniqueness is the PHP `GraphValidator` gate on save/validate (JS does not reimplement `Str::ascii`).

### Defaults

- **Drop:** `title` = type label, or `{type label} {n}` starting at 2 when taken (`Agent`, `Agent 2`).
- **Duplicate:** new `id`; unique title from source title (or type label if untitled).
- **Legacy graphs:** no automatic backfill. UI shows type label; codegen unchanged until the author commits a name.

### Inspector (Dify)

- Header: type icon | **inline text input** | type slug subtitle | close.
- Placeholder = current title or type-label fallback.
- Commit on blur and Enter. Escape restores the pre-edit value.
- Not a field inside `NodeConfigForm`.

### Canvas card

- Bold line = `title` or type-label fallback.
- Upper slug line stays `data.nodeType`.
- No double-click rename in v1 (NN-11 P2).

### Observability

- Snapshot `node_title` onto `__steps` at execution (graph title at that moment).
- Trace API exposes `node_title` next to `node_id`. Span `name` stays `node_id` for identity.
- UI (dock, timeline, pretty thread, inspect): prefer `node_title`; tooltip/secondary = `node_id`.
- Old spans without `node_title`: show `node_id`.

### Variable picker

- `getFlowNodeLabel` checks `title` (flow `data.title` or package `title`) **before** type `label`.

### i18n

- JS: `inspector.node_name_*` (or equivalent) in `resources/js/i18n/en.json` + `pt_BR.json`.
- PHP: `lang/{en,pt_BR}/validation.php` keys `node_title_unique`, `node_title_slug_unique`, `node_title_max`; `GraphValidator` via `StudioTranslator`.
- Type labels stay `nodes.{type}` (unchanged).

---

## Specific References

- Dify node inspector: name is an inline header field; clearing it does not wipe the previous name.
- Current id: `createNodeId` in `resources/js/studio-canvas/graph.js` (`{type}_${Date.now()}`).
- Current card: `WorkflowNode.jsx` header = type slug + type `data.label`.
- Current inspector: `NodeInspectorSidebar.jsx` static type label + type slug.
- Current codegen: `GraphTranspiler::nodeClassName` / `eventClassName`.
- Current traces: `WorkflowRunner::persistTraceSpans` uses `name` = `node_id`; `WorkflowTraceController` maps that to `node_id`.
- Current thread: `workflowOutput.js` sets `label: nodeId`.

---

## Agent's Discretion

- Exact input styling (underline vs bordered) as long as it reads as the header title, not a form row.
- Whether uniqueness toast appears on inspector blur (client) in addition to validate-on-save (required).
- Shared PHP helper name/location (`Support\NodeTitle` vs `Runtime\NodeTitle`) — one module used by validator, transpiler, and step snapshot.

---

## Deferred Ideas

- Double-click / inline rename on the canvas card (NN-11)
- Mutating `id` from a slug (rejected for v1)
- Auto-backfill titles on load (rejected: would change existing export class names)
- Sticky-note titles in the same uniqueness set
- Rewriting historical trace span `name` values
