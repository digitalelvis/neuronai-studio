# List Operator Context

**Gathered:** 2026-08-13
**Spec:** `.specs/features/list-operator/spec.md`
**Status:** Ready for design (naming locked; confirm if user overrides)

---

## Feature Boundary

A Logic canvas node that reads a **list** from workflow state, optionally applies a dot `path`, then filter (`where`) → sort → take N, and writes the resulting **array** to `as` plus optional first/last items. One default handle. Does not parse JSON, does not mutate the source mid-pipeline, does not fan-out items, does not fail the run on empty.

---

## Implementation Decisions

### Naming (locked unless overridden)

- **Node type:** `list_operator`
- **Palette label EN:** List Operator
- **Palette label pt_BR:** Operador de Lista
- **Category:** `logic` (next to Parse JSON / Set State)
- **Feature folder / IDs:** `list-operator` / `LO-xx`
- **Rejected:** extend Parse JSON with sort/take N / write-list — Parse JSON is parse + pick **one** + map to flat keys
- **Rejected:** ForEach / Split Out — fan-out per item is a different node (still deferred)
- **Rejected:** “Filter Array” — v1 also sorts, limits, and writes first/last
- **Deferred rename:** none. Inspector helper text: “Filter, sort, and take items from a state list.”

### Complementary to Parse JSON (M19)

| | Parse JSON | List Operator |
|---|--------------|---------------|
| Input | JSON string or array/object | Already a list (array) |
| Output | Flat keys from **one** item | **Array** + optional first/last |
| Parse JSON | Yes (`auto` / `always` / `never`) | No — chain Parse JSON first |
| Pick | `first` / `last` / `index` then map fields | Keep list; optional `first_as` / `last_as` are whole items |

Canonical chain when Tool `result` is a JSON string of a list:

```
Tool → Parse JSON (parse + path + as: items)
     → List Operator (where/sort/limit → as: filtered_items)
```

HITL “first unanswered question → flat keys” stays on Parse JSON. List Operator is for **keeping** the list (routing files, bounded batches).

### Mixed attachments hero

Canonical graph:

```
Start
  → List Operator (from_key: attachments, where type equals image, as: image_attachments)
       → Agent / LLM (vision on image_attachments)
  → List Operator (from_key: attachments, where type equals document, as: document_attachments)
       → LLM / RAG (documents only)
  → Stop
```

- Studio `state.attachments` items: `storage_key`, `mime_type`, `name`, `type` (`image` \| `audio` \| `video` \| `document`), `url`. **No `size`.**
- Filter is generic `ConditionEvaluator` per item — covers `type` / `mime_type` / `name` without a file DSL.
- Empty side (`on_empty` default **`clear`**) writes `null` so Condition/Loop can branch; run still leaves on `default`.
- No extra source handles per file type. Two nodes, not one node with image/document outputs.

### Where / sort / limit semantics

- Pipeline: **from_key → path → where → sort → limit → write `as` (+ first_as / last_as)**.
- `where` is a Condition-shaped rule `{ key, operator, value }` evaluated against each list item via `ConditionEvaluator` — not a free-text DSL.
- Object items: `key` is a field on the item (`type`, `mime_type`, `name`, …).
- Scalar items: `key` ignored; rule evaluates the scalar.
- `is_false` does **not** match missing/`null` fields (same as Condition / Parse JSON).
- Compound `where` (AND/OR) is P2 (`LO-10`).
- `sort_by` + `sort_dir` (`asc` default \| `desc`). Scalars with empty `sort_by` sort by value. Objects missing the sort field go **last**.
- `limit` integer ≥ 1 after filter+sort. No Dify cap of 1–20. Omit = full list.
- `first_as` / `last_as` read the list **after** filter+sort+limit.

### Parse / empty / types

- **No `parse_json`.** JSON string, single object, scalar, associative map → empty (`on_empty`), **do not** fail the run.
- `from_key` resolves with `WorkflowStateValue` (dot path), not `$state->get()`.
- `path` is optional `data_get` after resolve (nested list inside a wrapper object).
- Does not mutate the source: always write `as` (required). If `as` equals `from_key`, snapshot the list first, then replace.
- Native types kept (attachment objects stay arrays/objects). Interpolator stringifies if a later prompt uses the list.

### Inspector

- Fields: `from_key`, `path`, `where` (one rule), `sort_by`, `sort_dir`, `limit`, `as`, `first_as`, `last_as`, `on_empty` (clear | skip).
- `as` required. `first_as` / `last_as` optional.
- No Dify Type/MIME/Extension widgets in v1 (P2 presets). Authors type `type` / `equals` / `image`.

### Trace

On `step_completed`: `from`, `path`, `input_count`, `filtered_count`, `result_count`, `empty` (bool), `wrote` (destination keys). Counts are `null` when the source was not a list.

### Handles / empty

- One target, one source `default`. Empty does not fail; does not add `true`/`false` handles.
- `on_empty` default **`clear`**. `skip` leaves stale lists — document as dangerous for routing (vision Agent would see previous turn’s images).

---

## Specific References

- Analog: [Dify List Operator](https://docs.dify.ai/en/cloud/use-dify/nodes/list-operator) — filter / sort / take first N / first record / last record; outputs `result`, `first_record`, `last_record`.
- Studio attachments: `AttachmentController` + `docs/guides/agents/attachments.md` + `state.attachments` in `docs/guides/workflows/state-and-conditions.md`.
- Reuse: `ConditionEvaluator`, `WorkflowStateValue`, Parse JSON inspector patterns (`where`, `on_empty`).
- Market:
  - Dify: **List Operator** (this node) vs **Parameter Extractor** (LLM schema — not us)
  - n8n: **Filter** + **Sort** + **Limit** + **Item Lists**; **Split Out** = ForEach (we do **not**)
  - Langflow: **Data Operations** kitchen sink — too broad
  - Parse JSON (M19): Parse JSON + pick one + map — complementary, not this node

---

## Deferred Ideas

- ForEach / Split Out (array → loop body per item) — still a separate feature
- Compound `where` AND/OR (`LO-10` P2)
- Inspector presets for Type / MIME / Extension (sugar over Condition rules)
- Persist attachment `size` so Dify-style size filters become possible
- Document Extractor node (does not exist; do not invent in this feature)
- Auto-decode JSON strings inside List Operator (rejected: chain Parse JSON)
