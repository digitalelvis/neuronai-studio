# Canvas Node Names Specification

**Line:** `v3.1.x` · **Milestone:** M21 · **AD-034** · **Requirement IDs:** `NN-xx` · **Date:** 2026-08-13

**Context:** [context.md](./context.md) · **Tasks:** [tasks.md](./tasks.md)

## Problem Statement

Studio nodes are identified only by a generated graph `id` (`agent_1734…`). Canvas cards and the inspector show the **type** label (“Agent”), not an instance name. Logs, traces, playground thread, and variable inspect print the raw `id`. Native codegen does `Str::studly($id).'Node'` → `Agent1734123456789Node`. Authors cannot name a node for reading the canvas, debugging a run, or exporting readable PHP.

## Goals

- [ ] Every executable node can have a persisted `title` edited in the inspector header (Dify-style). Empty blur does not change the current value.
- [ ] Canvas, logs, traces, playground thread, and variable picker show `title` (id remains the technical key).
- [ ] Native codegen class/event names derive from a slug of `title` when present; `id` and `STUDIO_NODE_ID` stay unchanged.

## Naming

| | Value | Why |
|---|--------|-----|
| Field | `title` (top-level on the package graph node) | Matches Dify; stays out of type `data` config |
| EN | Node name | Authors search “name” / “rename” |
| pt_BR | Nome do nó | Inspector chrome |
| Not | Mutating `id` | Edges, `node:{id}`, checkpoints, old traces |
| Not | `data.label` | Already the **type** label in React Flow |

`id` is immutable. `title` is display + codegen slug source.

---

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Changing graph `id` / rewiring edges | High blast radius; bindings and in-flight checkpoints |
| Backfill titles on existing graphs | Would rename exported PHP classes (`Llm1Node` → `LlmNode`) |
| Sticky notes | Annotations, not executable / not codegen classes |
| Double-click rename on the canvas card | P2; v1 is inspector header only |
| Unique titles across workflows | Per-graph only |
| Migrating historical trace span names | Old spans keep `name` = `node_id` |

---

## User Stories

### P1: Persist instance title ⭐ MVP

**User Story**: As a Studio author, I want each node to keep a human name in graph JSON so reload, import, and save do not lose it.

**Why P1**: Without persistence the rest of the feature is cosmetic.

**Acceptance Criteria**:

1. WHEN a package graph node is saved THEN it SHALL include optional top-level `title` (string) alongside `id`, `type`, `position`, `data`.
2. WHEN `title` is absent or null THEN the graph SHALL remain valid (legacy graphs).
3. WHEN `toFlowNodes` / `toPackageGraph` round-trip THEN `title` SHALL survive and SHALL NOT be written into type `data` config.
4. WHEN `id` is assigned THEN it SHALL keep using `{type}_{Date.now()}` (or existing id) and SHALL NOT change when `title` changes.

**Independent Test**: Save a node with `title: "Qualificador de Lead"` → reload editor → JSON still has that `title` and the same `id`.

---

### P1: Inspector header (Dify) ⭐ MVP

**User Story**: As an author, I want to edit the node name in the inspector header so I do not hunt a field inside the type form.

**Why P1**: This is the authoring surface.

**Acceptance Criteria**:

1. WHEN a node is selected THEN the inspector header SHALL show an editable text field for `title`, with the type icon on the left and the type slug (`agent`) as subtitle.
2. WHEN the field is committed with a non-empty trimmed value THEN the node `title` SHALL update.
3. WHEN the field is committed empty (or whitespace-only) THEN the system SHALL restore the previous `title` (or the type-label fallback if still untitled) and SHALL NOT persist an empty string.
4. WHEN `readOnly` is true THEN the name SHALL not be editable.

**Independent Test**: Open inspector → change “Agent” to “Qualificador de Lead” → blur → card and JSON update. Clear the field → blur → name is still “Qualificador de Lead”.

---

### P1: Canvas card ⭐ MVP

**User Story**: As an author, I want the node card to show the instance name so the graph is readable without opening each inspector.

**Why P1**: Visual naming is half the request.

**Acceptance Criteria**:

1. WHEN a node has `title` THEN the card bold line SHALL show `title` (not the type label).
2. WHEN a node has no `title` THEN the card bold line SHALL show the type label from registry i18n (current behavior).
3. WHEN the type slug line is shown THEN it SHALL remain the type key (`agent`), not the instance name.

**Independent Test**: Named agent card reads `agent` / `Qualificador de Lead`. Untitled agent card still reads `agent` / `Agent`.

---

### P1: Unique titles + defaults ⭐ MVP

**User Story**: As an author, I want names unique in this workflow so logs and codegen slugs are unambiguous.

**Why P1**: Locked: free text, unique per workflow; slug collision blocks save.

**Acceptance Criteria**:

1. WHEN two executable nodes have the same title after trim + case-insensitive compare THEN save/validate SHALL fail with a clear error.
2. WHEN a node is dropped THEN it SHALL receive a default `title` equal to the type label, or `{type label} {n}` (`Agent 2`, `Agent 3`) when that base is taken.
3. WHEN a node is duplicated THEN the clone SHALL get a new `id` and a unique title derived from the source title (or type label if untitled).
4. WHEN two distinct titles produce the same codegen slug (`Str::studly(Str::ascii(trim(title)))`, after digit/empty prefix rules) THEN save/validate SHALL fail.
5. WHEN `title` exceeds 80 characters after trim THEN save/validate SHALL fail.
6. Sticky notes SHALL NOT participate in title or slug uniqueness.

**Independent Test**: Drop two Agents → titles `Agent` and `Agent 2`. Rename the second to `Agent` → validate errors. Rename to `Qualificador de Lead` and another node to `Qualificador De Lead` → slug collision error.

---

### P1: Codegen slug ⭐ MVP

**User Story**: As an author, I want exported PHP classes named from the node title so I do not ship `Agent1734…Node`.

**Why P1**: Codegen was an explicit goal; display-only would leave ugly class names.

**Acceptance Criteria**:

1. WHEN a node has a non-empty `title` THEN `nodeClassName` SHALL be `{slug}Node` and `eventClassName` SHALL be `{slug}Event`, with `slug = Str::studly(Str::ascii(trim(title)))`.
2. WHEN slug is empty or starts with a digit THEN the system SHALL prefix `Str::studly(type)` (then fall back to `Str::studly(id)` if still empty).
3. WHEN a node has no `title` THEN class/event names SHALL keep current behavior (`Str::studly(id).'Node'|'Event'`).
4. WHEN PHP is generated THEN `STUDIO_NODE_ID` SHALL remain the graph `id`, not the title or slug.

**Independent Test**: Node `id=agent_1734`, `title=Qualificador de Lead` → class `QualificadorDeLeadNode`, constant `STUDIO_NODE_ID = 'agent_1734'`. Untitled `llm_1` still exports `Llm1Node` / `Llm1Event`.

---

### P1: Logs, traces, thread ⭐ MVP

**User Story**: As an operator, I want run logs and traces to show the node name I set so I can follow a run without decoding timestamps.

**Why P1**: Observability was an explicit goal.

**Acceptance Criteria**:

1. WHEN a node step is recorded THEN the step SHALL include `node_id`, `node_type`, and `node_title` (string or null snapshot of the graph title at execution).
2. WHEN the trace API returns a node span THEN it SHALL include `node_id` (identity) and `node_title` when the snapshot exists.
3. WHEN the bottom dock, trace timeline, playground pretty thread, or variable inspect render a step THEN they SHALL prefer `node_title` and keep `node_id` as secondary/tooltip.
4. WHEN a historical span has no `node_title` THEN the UI SHALL show `node_id` (no migration).

**Independent Test**: Run a named agent → dock/thread/timeline show “Qualificador de Lead”. Old fixture span without title still shows `agent_1`.

---

### P1: Variable picker ⭐ MVP

**User Story**: As an author, I want state variable groups labeled with the node title so `{{agent_response}}` is grouped under “Qualificador de Lead”, not “Agent”.

**Why P1**: Same identity used while wiring the graph.

**Acceptance Criteria**:

1. WHEN `getFlowNodeLabel` (or successor) resolves a flow node with `title` THEN it SHALL return that title.
2. WHEN `title` is missing THEN it SHALL fall back to type label, then type, then `id` (current helper chain, with title first).

**Independent Test**: Named set-state node appears as source label “Reply copy” in the variable picker.

---

### P1: i18n + docs

**User Story**: As a Brazilian host, I want inspector chrome and validation copy in pt_BR, and canvas-editor docs that explain name vs id.

**Acceptance Criteria**:

1. WHEN locale is `en` / `pt_BR` THEN inspector placeholder/aria and uniqueness errors SHALL resolve from Studio catalogs.
2. WHEN GraphValidator rejects a title THEN the message SHALL go through `StudioTranslator` (`validation.node_title_*`) with English fallback.
3. WHEN docs update THEN `guides/workflows/canvas-editor.md` (and export notes if class names are documented) SHALL state: editable header name; empty blur keeps previous; `id` immutable; codegen uses slug of title.

**Independent Test**: `APP_LOCALE=pt_BR` → uniqueness error in Portuguese; canvas-editor mentions node name vs id.

---

### P2: Double-click rename on card

**User Story**: As an author, I want to rename a node by double-clicking the card title without opening the inspector.

**Why P2**: Dify v1 analog is the inspector header; card inline edit is extra chrome.

**Acceptance Criteria**:

1. WHEN the card title is double-clicked THEN it SHALL enter the same commit rules as the inspector (empty = revert, uniqueness on blur/save).

**Independent Test**: Double-click card → type name → blur → title persists.

---

## Edge Cases

- WHEN `title` is missing on load THEN display uses type label; codegen uses `id`; header starts with that fallback; first successful commit persists `title`.
- WHEN title is only emoji/punctuation and slug is empty after ascii/studly THEN uniqueness of the **display** title still applies; codegen falls back to `id`; save is allowed unless another node shares that display title.
- WHEN `title` has leading/trailing whitespace THEN commit stores the trimmed value.
- WHEN two titles differ only by case (`Agent` / `agent`) THEN they SHALL conflict.
- WHEN import JSON includes `title` inside `data` and not top-level THEN it SHALL be ignored (not a config field).
- WHEN start/stop are renamed THEN uniqueness SHALL include them (exactly one start still required by id/type rules).
- WHEN a run starts THEN `node_title` is snapshotted from the graph at that moment; later renames do not rewrite past steps.

---

## Requirement Traceability

| ID | Story | Priority | Status |
| ---- | ----- | -------- | ------ |
| NN-01 | Persist top-level `title`; `id` immutable | P1 | Pending |
| NN-02 | Inspector header edit; empty blur reverts | P1 | Pending |
| NN-03 | Canvas card shows `title` | P1 | Pending |
| NN-04 | Unique titles; drop/duplicate defaults | P1 | Pending |
| NN-05 | GraphValidator title + slug + max 80 | P1 | Pending |
| NN-06 | Codegen slug; untitled keeps `studly(id)` | P1 | Pending |
| NN-07 | Step/trace snapshot `node_title`; old spans fallback | P1 | Pending |
| NN-08 | Dock, thread, inspect, variable picker | P1 | Pending |
| NN-09 | i18n EN + pt_BR | P1 | Pending |
| NN-10 | Docs canvas-editor (+ export if needed) | P1 | Pending |
| NN-11 | Double-click rename on card | P2 | Pending |

**Coverage:** 11 total, mapped in [tasks.md](./tasks.md)

---

## Success Criteria

- [ ] Author names a node in the inspector header; empty blur keeps the previous name
- [ ] Canvas card, dock, traces, playground thread, and variable picker show that name
- [ ] Export of a titled node produces `{Slug}Node` with `STUDIO_NODE_ID` = original `id`
- [ ] Untitled legacy graphs still export `Llm1Node` / `Llm1Event`
- [ ] Duplicate titles and slug collisions fail validate/save
- [ ] Tests cover round-trip, empty blur semantics (JS), uniqueness, slug/codegen, snapshot + legacy fallback
