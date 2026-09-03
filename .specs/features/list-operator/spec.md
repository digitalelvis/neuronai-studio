# List Operator Specification

**Line:** `v3.1.x` · **Milestone:** M20 · **AD-032** · **Requirement IDs:** `LO-xx` · **Date:** 2026-08-13

**Context:** [context.md](./context.md)

## Problem Statement

Studio workflows share a mutable list in state (`attachments`, tool arrays, Parse JSON output) but have no node that **keeps the list** after filter/sort/limit. Parse JSON (M19) parseia JSON e achata **um** item em chaves planas. Set State interpolates templates; Condition branches on a scalar. Mixed uploads (`state.attachments` with `type` image/document/audio/video) therefore cannot be split into focused lists for a vision Agent vs a text LLM without Invoke or host PHP.

## Goals

- [ ] Palette node `list_operator` (Logic, 1 default handle) that filter → sort → take N and writes the resulting **array** plus optional first/last items.
- [ ] Two nodes on `attachments` (`type equals image` / `type equals document`) produce `image_attachments` and `document_attachments` without failing the run when a side is empty.
- [ ] Already-array values work without JSON decode. Misses / non-lists do not fail the run. Trace records from/path/counts/wrote.

## Naming

| | Value | Why |
|---|--------|-----|
| Type | `list_operator` | Searchable; matches [Dify List Operator](https://docs.dify.ai/en/cloud/use-dify/nodes/list-operator) |
| EN | List Operator | Authors look for list/array ops after attachments or Tool lists |
| pt_BR | Operador de Lista | Same product name as Dify, in Portuguese |
| Not | Parse JSON | M19: parse + pick **one** item + map to flat keys |
| Not | ForEach / Split Out | Fan-out per item is a different node (deferred) |
| Not | Filter Array | Too narrow; v1 also sorts, limits, and writes first/last |

Studio uses **shared WorkflowState**, not Dify typed `array[file]` streams. One Logic node that reads/writes state lists is the right product.

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Fan-out / ForEach / Split Out | Different node; list iteration as loop body |
| Handles per file type | Routing is two List Operator nodes, not extra source handles |
| Parse JSON / JSONPath / jq | Chain Parse JSON (M19) when the source is a JSON string |
| Mutate the source array | Always write a destination key (`as`) |
| Compound `where` (AND/OR) | One Condition rule in v1; Condition already has `rules[]` for P2 |
| Filter by `size` | Studio attachments do not persist file size |
| Dify Type/MIME/Extension widgets | v1 = one Condition rule; presets are P2 inspector sugar |
| Document Extractor | Does not exist in Studio |

---

## User Stories

### P1: Palette + inspector ⭐ MVP

**User Story**: As a Studio author, I want a List Operator node next to Parse JSON / Set State so I can filter a state list without Invoke.

**Why P1**: Without authoring surface there is no feature.

**Acceptance Criteria**:

1. WHEN `node_types.list_operator` is registered THEN the palette SHALL list it under Logic with i18n labels EN “List Operator” / pt_BR “Operador de Lista”.
2. WHEN the node is selected THEN the inspector SHALL persist `from_key`, optional `path`, optional `where` `{ key, operator, value }`, optional `sort_by`, optional `sort_dir` (`asc` \| `desc`), optional `limit` (integer ≥ 1), `as`, optional `first_as`, optional `last_as`, and `on_empty` (`clear` \| `skip`).
3. WHEN the node is on the canvas THEN it SHALL expose one target and one source handle `default`.
4. WHEN `from_key` is empty OR `as` is empty THEN save/run validation SHALL reject with a clear error.

**Independent Test**: Drop node → fill `from_key: attachments`, where `type equals image`, `as: image_attachments` → graph JSON persists; palette shows under Logic.

---

### P1: Runtime filter + write array ⭐ MVP

**User Story**: As an operator, I want mixed `state.attachments` split by `type` so a vision Agent receives only images and a text LLM receives only documents.

**Why P1**: This is the mixed-upload hole Dify solves with List Operator.

**Acceptance Criteria**:

1. WHEN `from_key` is resolved THEN the executor SHALL use `WorkflowStateValue` (dot path on state), not top-level `$state->get()` only.
2. WHEN `path` is set THEN the executor SHALL apply Laravel `data_get` on the resolved value before treating it as the list.
3. WHEN the value after `path` is a list THEN the executor SHALL filter items with `where` (if present) using `ConditionEvaluator` against each **object** item’s fields (`key` = field on the item).
4. WHEN a list item is a scalar THEN `where.key` SHALL be ignored and the rule SHALL evaluate against the scalar itself.
5. WHEN `where` is omitted THEN all list items SHALL remain candidates.
6. WHEN the node completes THEN the executor SHALL write the resulting array to state key `as` (does not mutate the source) and SHALL leave via handle `default` even if the result is empty.
7. WHEN `parse_json` is not a field THEN a JSON **string** SHALL be treated as empty (not decoded). Authors SHALL chain Parse JSON first.

**Independent Test**: `attachments` = image + PDF + image → where `type equals image` + `as: image_attachments` → destination has two image objects; source `attachments` unchanged.

---

### P1: Sort + take N ⭐ MVP

**User Story**: As an author, I want to sort a filtered list and keep only the first N items so downstream nodes see a bounded, ordered array.

**Why P1**: Dify List Operator is filter + sort + selection; without sort/limit the node is only a filter.

**Acceptance Criteria**:

1. WHEN `sort_by` is set THEN the executor SHALL sort the **filtered** list by that field (`asc` default, `desc` when `sort_dir` is `desc`).
2. WHEN items are scalars AND `sort_by` is empty THEN the executor SHALL sort by the scalar values.
3. WHEN an object item is missing `sort_by` THEN that item SHALL sort after items that have the field.
4. WHEN `limit` is a positive integer THEN the executor SHALL take the first N items of the **filtered and sorted** list.
5. WHEN `limit` is omitted THEN the executor SHALL keep the full filtered/sorted list.

**Independent Test**: Three documents sorted by `name` desc, `limit: 1` → `as` is a one-item array with the last name alphabetically.

---

### P1: First / last of the result ⭐ MVP

**User Story**: As an author, I want the first and last records of the processed list written to optional keys so I can feed a “primary” file to an Agent without a second Parse JSON.

**Why P1**: Dify outputs `result`, `first_record`, and `last_record`. Studio maps those to state keys.

**Acceptance Criteria**:

1. WHEN `first_as` is set THEN the executor SHALL write the first item of the **filtered + sorted + limited** list to that state key.
2. WHEN `last_as` is set THEN the executor SHALL write the last item of that same result list to that state key.
3. WHEN the result list is empty THEN `first_as` / `last_as` SHALL follow `on_empty` (clear → `null`; skip → leave existing).

**Independent Test**: Two images after filter → `first_as: primary_image` is the first image object; `last_as: other_image` is the second.

---

### P1: Empty handling ⭐ MVP

**User Story**: As an author, I want a missing or empty list to clear destination keys so a later Condition/Loop can branch on `empty image_attachments` instead of failing the run.

**Why P1**: Mixed uploads often have only one modality; empty side must be a valid path.

**Acceptance Criteria**:

1. WHEN the source is missing, `path` misses, the value is not a list, the list is empty, or no `where` match AND `on_empty` is `clear` (default) THEN `as`, `first_as`, and `last_as` (if configured) SHALL be set to `null`.
2. WHEN `on_empty` is `skip` THEN the executor SHALL not write destinations (leave existing values).
3. WHEN the value is a JSON string, a single object, or a scalar THEN the executor SHALL treat it as empty and SHALL NOT fail the run.
4. WHEN the node completes with an empty result THEN it SHALL still leave via handle `default`.

**Independent Test**: Only PDFs in `attachments` + image filter + `on_empty: clear` → `image_attachments` is `null`; run continues.

---

### P1: Trace + codegen + docs

**User Story**: As an operator, I want traces and exported PHP to show how the list was reduced so I can debug mixed-file routing without Invoke.

**Acceptance Criteria**:

1. WHEN the node completes THEN `step_completed` payload SHALL include `from`, `path`, `input_count` (int or null), `filtered_count` (int or null), `result_count` (int or null), `empty` (bool), `wrote` (destination map of keys written).
2. WHEN codegen runs THEN it SHALL emit equivalent PHP (`WorkflowStateValue` + optional `data_get` + filter/sort/limit + `$state->set` for `as` / `first_as` / `last_as`).
3. WHEN docs update THEN logic-nodes, state-and-conditions, custom-node-types, and attachments SHALL describe List Operator vs Parse JSON vs ForEach; mixed-upload example SHALL use two List Operator nodes on `attachments`.

**Independent Test**: Unit test asserts trace keys; codegen snapshot contains filter/sort/`$state->set`; docs mention `list_operator` and contrast with `parse_json`.

---

### P2: Mixed attachments example graph

**User Story**: As an author, I want a documented (or template) mixed-upload graph so I do not send PDFs to a vision Agent.

**Why P2**: Runtime works without a template; the Dify mixed-file pattern is the motivating demo.

**Acceptance Criteria**:

1. WHEN docs (or a template) show mixed uploads THEN they SHALL use two List Operator nodes (`type equals image` → `image_attachments`, `type equals document` → `document_attachments`) and SHALL NOT claim a Document Extractor node exists.
2. WHEN the example mentions empty sides THEN it SHALL state that `on_empty: clear` writes `null` and the run continues on `default`.

**Independent Test**: Follow the doc graph with one JPEG + one PDF → vision node state has images only; text node state has documents only.

---

### P2: Compound where

**User Story**: As an author, I want AND/OR rules on list items so I can filter `type equals image` AND `name contains hero` without chaining two nodes.

**Why P2**: One Condition rule covers the mixed-upload hero; compound rules already exist on Condition nodes.

**Acceptance Criteria**:

1. WHEN `where.logic` is `all` or `any` and `where.rules[]` is set THEN the executor SHALL evaluate those rules per item the same way Condition compound rules work.
2. WHEN `rules` is absent THEN the flat `{ key, operator, value }` SHALL be used (v1 behavior).

**Independent Test**: Images named `hero.png` and `other.png` + AND type=image + name contains hero → `as` has one item.

---

## Edge Cases

- WHEN `from_key` path is missing THEN empty (clear/skip).
- WHEN `path` is empty and `from_key` already resolves to a list THEN that list is the source.
- WHEN `limit` is `0`, negative, or non-integer THEN validation SHALL reject (do not treat as “no limit”).
- WHEN `sort_dir` is omitted THEN `asc`.
- WHEN `where.operator` is `is_false` and the field is missing or `null` THEN that item SHALL NOT match (same as Condition / Parse JSON).
- WHEN `first_as` / `last_as` are omitted THEN only `as` is written.
- WHEN the result has one item THEN `first_as` and `last_as` SHALL be that same item.
- WHEN associative maps (JSON objects) are the value after `path` THEN treat as empty (not a list). PHP lists are 0-based sequential arrays.
- WHEN `as` equals `from_key` THEN the executor SHALL still write the processed list to that key (replace, not in-place mutate mid-pipeline). Source snapshot is taken before writes.

---

## Requirement Traceability

| ID | Story | Priority | Status |
| ---- | ----- | -------- | ------ |
| LO-01 | Palette + inspector + handles + i18n | P1 | Pending |
| LO-02 | Runtime filter + write `as` | P1 | Pending |
| LO-03 | Sort + take N | P1 | Pending |
| LO-04 | `first_as` / `last_as` of processed list | P1 | Pending |
| LO-05 | `on_empty` clear/skip; GraphValidator (`from_key` + `as`) | P1 | Pending |
| LO-06 | Trace payload | P1 | Pending |
| LO-07 | Native codegen | P1 | Pending |
| LO-08 | Docs (logic-nodes, state, attachments vs Parse JSON) | P1 | Pending |
| LO-09 | Mixed attachments example graph | P2 | Pending |
| LO-10 | Compound `where` (AND/OR) | P2 | Pending |

**Coverage:** 10 total, 0 mapped to tasks

---

## Success Criteria

- [ ] Mixed `attachments` → two List Operator nodes → image list and document list in state
- [ ] Empty modality → destinations `null` (`on_empty: clear`) → run continues on `default`
- [ ] Already-array values filter/sort/limit without JSON decode
- [ ] JSON string source is empty (chain Parse JSON); does not fail the run
- [ ] Trace shows from, path, counts, empty, wrote
- [ ] Tests cover attachments-by-type, scalar list + where, sort missing field, limit, first/last, `on_empty` skip vs clear, non-list source
