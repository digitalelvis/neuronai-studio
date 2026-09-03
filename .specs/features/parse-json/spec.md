# Parse JSON Specification

**Line:** `v3.1.x` · **Milestone:** M19 · **AD-033** · **Requirement IDs:** `PJ-xx` · **Date:** 2026-08-13

**Context:** [context.md](./context.md)

## Problem Statement

Tool and MCP nodes store `{ name, result }` with `result` as a **JSON string** (Neuron `Tool::getResult(): string`). Set State interpolates `{{chave}}` / dot paths on **arrays**, but does not parse JSON and cannot pick “first item where X”. Human prompts and Tool `$params` need **flat** keys (`current_question_text`, `current_question_id`). HITL loops over a questions list therefore require Invoke or host PHP today.

## Goals

- [ ] Palette node `parse_json` (Logic, 1 default handle) that decode → path → filter → pick → map into flat state keys.
- [ ] Tool JSON string → Human shows extracted question text; empty list / `next_question: null` → mapped keys null → Loop `empty` exits.
- [ ] Already-array values work without decode. Misses do not fail the run. Trace records from/path/item/wrote.

## Naming

| | Value | Why |
|---|--------|-----|
| Type | `parse_json` | Make / Power Automate / Langflow legacy **Parse JSON**; PHP mental model `json_decode` |
| EN | Parse JSON | Verb-first, same as the automation market |
| pt_BR | Parsear JSON | Loanword already used by BR authors; not “Analisar” |
| Not | JSON Extract | Collides with MySQL `JSON_EXTRACT()` for this Laravel audience |
| Not | JSON Parse | Reads as JS `JSON.parse()` — decode-only |
| Not | Extract State | LLM “parameter extract” (Dify); “state” is Studio jargon |
| Not | Data Operations | Langflow umbrella; too wide for v1 |

The node still **path / where / pick / map** after decode. The name highlights the entry problem (Tool `result` is a string). Inspector fields show the rest. Market splits this into 2–4 nodes; Studio shared-state graphs keep one composite node.

Decode mode field is `decode` (`auto` \| `always` \| `never`), not `parse_json`, so it does not collide with the node type.

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Full JSONPath / jq | Dot path + first/where + map covers Tool→Human |
| Multiple extracts in one node | Keep inspector simple; chain nodes |
| Mutate the source array | Backend cursor (`next_question`) is the HITL source of truth |
| Fan-out / ForEach / Split Out | Different node; list iteration in state |
| Auto-decode inside Tool/MCP executor | Companion feature (deferred) |
| Compound where (AND/OR) | One Condition rule in v1 |
| LLM Parameter Extractor | Different product (schema from prose) |

---

## User Stories

### P1: Palette + inspector ⭐ MVP

**User Story**: As a Studio author, I want a Parse JSON node next to Set State so I can turn a Tool JSON string into flat keys without Invoke.

**Why P1**: Without authoring surface there is no feature.

**Acceptance Criteria**:

1. WHEN `node_types.parse_json` is registered THEN the palette SHALL list it under Logic with i18n labels EN “Parse JSON” / pt_BR “Parsear JSON”.
2. WHEN the node is selected THEN the inspector SHALL persist `from_key`, `decode` (`auto` \| `always` \| `never`), `path`, optional `where` `{ key, operator, value }`, `pick` (`first` \| `last` \| `index`) + `index`, `map` rows `{ from, to }`, optional `as`, and `on_empty` (`clear` \| `skip`).
3. WHEN the node is on the canvas THEN it SHALL expose one target and one source handle `default`.
4. WHEN `from_key` is empty OR (`map` is empty AND `as` is empty) THEN save/run validation SHALL reject with a clear error.

**Independent Test**: Drop node → fill `from_key: questions_result.result`, path, two map rows → graph JSON persists; palette shows under Logic.

---

### P1: Runtime decode + path + map ⭐ MVP

**User Story**: As an operator, I want a Tool JSON string decoded and a nested field written to a flat key so Human can interpolate `{{current_question_text}}`.

**Why P1**: This is the Tool→Human hole.

**Acceptance Criteria**:

1. WHEN `from_key` is resolved THEN the executor SHALL use `WorkflowStateValue` (dot path on state), not top-level `$state->get()` only.
2. WHEN `decode` is `auto` and the value is a string of a JSON object or array THEN the executor SHALL `json_decode` once to an associative array; WHEN the value is already an array/object THEN it SHALL skip decode; WHEN `never` THEN it SHALL not decode.
3. WHEN `path` is set THEN the executor SHALL apply Laravel `data_get` on the (possibly decoded) value.
4. WHEN `map` rows are set THEN for each `{ from, to }` the executor SHALL write `data_get(item, from)` to state key `to`, keeping native types.
5. WHEN `as` is set THEN the executor SHALL write the chosen item (object/array/scalar) to that state key.
6. WHEN the node completes THEN it SHALL leave via handle `default` even if the extract is empty.

**Independent Test**: State `questions_result.result` = JSON string with `data.data.questions[0].question_text` → after node, `current_question_text` equals that string; Human prompt interpolates it.

---

### P1: List where + pick ⭐ MVP

**User Story**: As an author, I want to pick the first unanswered question from a list so a Loop can ask one question per iteration.

**Why P1**: Generate-questions returns a list; save returns a single `next_question` object.

**Acceptance Criteria**:

1. WHEN the value after `path` is a list THEN the executor SHALL filter items with `where` (if present) using `ConditionEvaluator` against each item’s fields, then apply `pick` on the **filtered** list (`first` default).
2. WHEN `pick` is `index` THEN `index` SHALL be 0-based into the filtered list.
3. WHEN the value after `path` is a single object or scalar THEN `where` and `pick` SHALL be ignored and that value SHALL be the chosen item.
4. WHEN `where.operator` is `is_false` and the field is missing or `null` THEN that item SHALL NOT match (same as Condition nodes).

**Independent Test**: Three questions, first `is_answered: true`, second false → `pick: first` + where `is_answered is_false` maps the second; `next_question` object with no pick maps that object.

---

### P1: Empty handling ⭐ MVP

**User Story**: As an author, I want a missing item to clear flat keys so Loop `empty current_question_text` exits instead of failing the run.

**Why P1**: End of questions / `next_question: null` must exit HITL.

**Acceptance Criteria**:

1. WHEN no item is found (missing key, invalid JSON, `path` miss, empty list, no where match, `next_question` null) AND `on_empty` is `clear` (default) THEN each mapped `to` key and `as` SHALL be set to `null`.
2. WHEN `on_empty` is `skip` THEN the executor SHALL not write destinations (leave existing values).
3. WHEN JSON is invalid THEN the executor SHALL treat as empty and SHALL NOT fail the run.
4. WHEN Loop `state_key` is a mapped key, `operator` is `empty`, and that key is `null` THEN Loop SHALL route `exit`.

**Independent Test**: Parse JSON after save with `next_question: null` + `on_empty: clear` → `current_question_text` null → Loop exits to generate-content.

---

### P1: Trace + codegen + docs

**User Story**: As an operator, I want traces and exported PHP to show what was parsed so I can debug HITL without Invoke.

**Acceptance Criteria**:

1. WHEN the node completes THEN `step_completed` payload SHALL include `from`, `path`, `parsed` (bool), `empty` (bool), `item` (chosen value or null), `wrote` (destination map).
2. WHEN codegen runs THEN it SHALL emit equivalent PHP (`WorkflowStateValue` + `json_decode` + `data_get` + optional filter/pick + `$state->set` for map/`as`).
3. WHEN docs update THEN logic-nodes, state-and-conditions, custom-node-types, and overview SHALL describe Parse JSON vs Set State vs List Operator vs Invoke; HITL questions example SHALL use this node.

**Independent Test**: Unit test asserts trace keys; codegen snapshot contains `json_decode` / `data_get`; docs mention `parse_json`.

---

### P2: HITL questions example graph

**User Story**: As an author, I want a documented (or template) questions loop so I do not wire Loop `not_empty` on the raw tool blob.

**Why P2**: Runtime works without a template; the resource-flow graph is the motivating demo.

**Acceptance Criteria**:

1. WHEN docs (or a template) show the questions HITL THEN they SHALL use two Parse JSON nodes, Loop `empty` on `current_question_text`, Human `{{current_question_text}}`, and save params `$human_response` + `$current_question_id`.
2. WHEN the example mentions Loop semantics THEN it SHALL state that Loop is until (condition true → exit).

**Independent Test**: Follow the doc graph on a fixture tool result → Human prompt is the first unanswered text; after a null `next_question`, flow exits the loop.

---

## Edge Cases

- WHEN `decode` is `always` and the value is a non-JSON string THEN treat as empty (do not fail).
- WHEN `decode` is `auto` and the string is a JSON scalar (`"hello"`, `42`, `true`) THEN do not treat it as an object/array extract source; leave as scalar (path/where/pick no-op; map `from` empty uses the scalar).
- WHEN `index` is out of range THEN treat as empty.
- WHEN a map `from` misses on a found item THEN write `null` for that `to` only (other rows still write).
- WHEN `from_key` path is missing THEN empty (clear/skip).
- WHEN the decoded JSON is a list at the root and `path` is empty THEN the list is the extract source.
- WHEN `where` is omitted THEN all list items are candidates for `pick`.

---

## Requirement Traceability

| ID | Story | Priority | Status |
| ---- | ----- | -------- | ------ |
| PJ-01 | Palette + inspector + handles | P1 | Pending |
| PJ-02 | Runtime decode + path + map/`as` | P1 | Pending |
| PJ-03 | List where + pick; object ignores pick | P1 | Pending |
| PJ-04 | `on_empty` clear/skip; invalid JSON does not fail | P1 | Pending |
| PJ-05 | GraphValidator (`from_key` + map/`as`) | P1 | Pending |
| PJ-06 | Trace payload | P1 | Pending |
| PJ-07 | Native codegen | P1 | Pending |
| PJ-08 | Docs (logic-nodes, state, HITL example) | P1 | Pending |
| PJ-09 | i18n EN + pt_BR labels | P1 | Pending |
| PJ-10 | HITL questions example graph | P2 | Pending |

**Coverage:** 10 total, 0 mapped to tasks

---

## Success Criteria

- [ ] Tool result JSON string → Human shows extracted text with no Invoke
- [ ] Empty list / `next_question: null` → destinations null → Loop empty exits
- [ ] Already-array values extract the same way
- [ ] Trace shows from, path, item or empty, wrote
- [ ] Tests cover string JSON, array, object-without-pick, invalid JSON, where+first, index on filtered list, `on_empty` skip vs clear
