# Parse JSON Context

**Gathered:** 2026-08-13
**Spec:** `.specs/features/parse-json/spec.md`
**Status:** Ready for design (naming locked as Parse JSON)

---

## Feature Boundary

A Logic canvas node that reads one value from workflow state, optionally `json_decode`s a JSON string, applies dot `path` + optional list `where`/`pick`, and writes one or more **flat** state keys. One default handle. Does not mutate the source array, does not fan-out items, does not fail the run on empty.

---

## Implementation Decisions

### Naming (locked)

- **Node type:** `parse_json`
- **Palette label EN:** Parse JSON
- **Palette label pt_BR:** Parsear JSON
- **Category:** `logic` (next to Set State)
- **Feature folder / IDs:** `parse-json` / `PJ-xx`
- **Decode field:** `decode` (`auto` \| `always` \| `never`) — not named `parse_json`, to avoid colliding with the node type
- **Rejected:** `json_extract` / “JSON Extract” — MySQL `JSON_EXTRACT()` is the first hit for this Laravel audience
- **Rejected:** “JSON Parse” — JS `JSON.parse()` = decode-only; market word order is **Parse JSON** (Make, Power Automate, Langflow)
- **Rejected:** `extract_state` / “Extract State” — LLM parameter extract (Dify); “state” is Studio jargon
- **Rejected:** `data_operations` / “Data Operations” — Langflow kitchen sink
- **Inspector helper:** “Decode a JSON string (Tool/MCP result) and write flat keys.” Path, where, pick, and map stay visible so the name does not hide the rest of the pipeline.

### HITL questions pattern (resource flow)

Canonical graph:

```
tool_generate_questions
  → Parse JSON (list → first unanswered → flat keys)
  → Loop (exit when empty current_question_text)
       continue → Human {{current_question_text}}
                → tool_save_answer ($human_response, $current_question_id)
                → Parse JSON (next_question object → same keys)
                → Loop
       exit → generate content
```

- Loop is **until** (condition true → `exit`). Use operator `empty` on `current_question_text`.
- Human `output_key` must match the save tool param (`human_response`, not `human_answer`).
- `on_empty` default **`clear`** (write null to mapped keys). `skip` is available but dangerous in this loop (stale question text → re-ask / resave).

### Where / pick semantics

- `where` is a Condition-shaped rule `{ key, operator, value }` evaluated against each list item via `ConditionEvaluator` — not a free-text DSL (`is_answered is_false`).
- Pipeline: **from_key → decode if string → path → (if list) where → pick → map**.
- After `path`, a **single object** (`next_question`) ignores `pick`/`where`.
- `pick`: `first` | `last` | `index` (0-based) of the **filtered** list.
- `is_false` does **not** match missing/`null` fields (same as Condition). Document: unanswered questions without `is_answered` should use `empty` or a later compound where.

### Decode / empty / types

- `decode`: default **auto** — decode when the resolved value is a string that is valid JSON object/array; leave other strings as-is; no-op on arrays/objects.
- Invalid JSON → treat as empty (`on_empty`), **do not** fail the run. One decode only (no recursive unwrap).
- Envelope `data.data` stays in `path` (host API shape, not Studio).
- Mapped values keep native types (int `id` stays int). Interpolator stringifies for Human prompts.
- `from_key` resolves with `WorkflowStateValue` (dot path), not `$state->get()`.

### Inspector

- Fields: `from_key`, `decode` (auto / always / never), `path`, `where` (one rule), `pick` + `index`, `map` rows `{ from, to }`, `on_empty` (clear | skip).
- Map rows like Set State key pairs. Empty `to` skipped.
- Optional v1: if `map` empty, write the chosen item to `as` key (default unset = no write besides map). Spec requires at least one map row **or** `as`.

### Trace

On `step_completed`: `from`, `path`, `parsed` (bool), `empty` (bool), `item` (chosen object or null), `wrote` (map of destination keys).

### Complementary (not this node)

Captured as deferred: Tool/MCP `decode_result`; Tool params via `WorkflowStateValue`; ForEach node; docs fix that `{{placeholders}}` already support dots. List Operator (M20) keeps lists; Parse JSON picks one item and flattens keys.

---

## Specific References

- Host graph: `resource-flow-suggested.json` (GenerateResourceQuestions → Loop → Human → SaveResourceAnswer).
- Neuron `Tool::getResult(): string` — arrays are `json_encode`d. Tool/MCP executors store `{ name, result }`.
- Market analogs (collapsed into one node because Studio is shared-state, not n8n item-stream):
  - Make / Power Automate / Langflow (legacy): **Parse JSON**
  - Langflow now: **Data Operations** (Path Selection, JQ)
  - Flowise: **JSONPathExtractor** (`lodash.get`) — path only; name collides with MySQL
  - n8n: **Edit Fields (Set)** + expressions; **Split Out** for fan-out (we do **not** fan-out)
  - Dify: **Parameter Extractor** = LLM schema extract — **different product**
  - MySQL: `JSON_EXTRACT(doc, path)` — same metaphor, wrong search collision for a Laravel canvas

---

## Deferred Ideas

- Tool/MCP auto `json_decode` of `result` (P2 companion)
- Tool `$param` resolution via `WorkflowStateValue` + `{{templates}}`
- ForEach / Split Out node (array → loop body per item) when the list lives only in state
- Compound `where` (AND/OR), JSONPath, multiple extracts per node, mutate source array
- Langflow-style Data Operations kitchen sink
