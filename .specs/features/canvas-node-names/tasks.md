# Canvas Node Names — Tasks

**Spec**: [spec.md](./spec.md)  
**Context**: [context.md](./context.md)  
**Design**: skipped — inline per task  
**Status**: Ready for Execute  
**Linha sugerida:** `v3.1.x` · milestone **M21** · **AD-034** · branch `feat/canvas-node-names`

---

## Execution Plan

```
NN-T1 → NN-T2 [P]
NN-T1 → NN-T3 [P]
NN-T1 → NN-T5 [P]
NN-T1 → NN-T6 [P]
NN-T3 → NN-T4
NN-T6 → NN-T7
NN-T3 → NN-T7
NN-T2, NN-T4, NN-T5, NN-T7 → NN-T8
```

Phase 1 (sequential): `NN-T1`  
Phase 2 (parallel after T1): `NN-T2`, `NN-T3`, `NN-T5`, `NN-T6`  
Phase 3: `NN-T4` (after T3), `NN-T7` (after T3 + T6)  
Phase 4: `NN-T8` (docs)

TESTING.md is absent. PHP layers = phpunit (parallel-safe per file). React chrome = no dedicated JS runner; behavior covered by phpunit node `--input-type=module` scripts where a helper is extractable, otherwise Done-when checklist + `npm run build`.

---

## Task Breakdown

### NN-T1 — `NodeTitle` helper (NN-04, NN-05, NN-06)

**What**: Shared PHP helper: normalize (trim / empty → null), uniqueness key (mb_strtolower), slug (`studly(ascii)` + type prefix + id fallback), unique default (`{base}`, `{base} 2`, …).  
**Where**: `src/Support/NodeTitle.php` (or `src/Runtime/NodeTitle.php` if Support is wrong)  
**Depends on**: —  
**Reuses**: `Illuminate\Support\Str::ascii` / `studly`  
**Inline design**: Pure static (or invokable) functions; no DB. Max length constant `80`.  
**Requirement**: NN-04, NN-05, NN-06

**Done when**:

- [ ] `normalize('  Foo  ')` → `'Foo'`; whitespace-only → `null`
- [ ] uniqueness keys of `Agent` and `agent` match
- [ ] `Qualificador de Lead` → slug `QualificadorDeLead`
- [ ] empty/digit-leading slug prefixes type then falls back to id
- [ ] `uniqueDefault('Agent', ['agent'])` → `Agent 2`

**Tests**: unit `tests/Support/NodeTitleTest.php` (or `tests/Runtime/NodeTitleTest.php`)  
**Gate**: `./vendor/bin/phpunit tests/Support/NodeTitleTest.php` (adjust path)  
**Commit**: `feat(canvas): add NodeTitle slug and uniqueness helper`

---

### NN-T2 — GraphValidator title + slug [P]

**What**: Validate optional `title`: max 80, unique among executable nodes (trim + case-insensitive), unique derived slugs when slug is non-empty; ignore notes; ignore missing title.  
**Where**: `src/Runtime/GraphValidator.php`, `lang/en/validation.php`, `lang/pt_BR/validation.php`  
**Depends on**: NN-T1  
**Reuses**: `StudioTranslator::get`, existing node-id uniqueness loop  
**Requirement**: NN-05, NN-09

**Done when**:

- [ ] Duplicate titles (`Agent` / `agent`) → invalid + translated message
- [ ] Distinct titles, same slug → invalid
- [ ] Missing titles → valid
- [ ] Title inside `data` only → not treated as instance title
- [ ] Notes excluded

**Tests**: `tests/GraphValidatorTest.php` (add cases)  
**Gate**: `./vendor/bin/phpunit tests/GraphValidatorTest.php`  
**Commit**: `feat(canvas): validate unique node titles and codegen slugs`

---

### NN-T3 — Graph JSON round-trip + drop/duplicate [P]

**What**: Persist top-level `title` in `toPackageGraph` / `toFlowNodes` / `buildFlowNode`; default unique title on drop; unique title on duplicate; selection payload includes `title`.  
**Where**: `resources/js/studio-canvas/graph.js`, `resources/js/studio-canvas/WorkflowCanvas.jsx`  
**Depends on**: NN-T1 (algorithm; JS mirrors **title** uniqueness only, not `Str::ascii`)  
**Reuses**: `createNodeId`, existing `duplicateNode`  
**Inline design**: Flow field `data.title`. Do **not** put `title` in `data.config`. Keep `data.label` as type label.  
**Requirement**: NN-01, NN-04

**Done when**:

- [ ] Round-trip keeps `title` outside config
- [ ] Drop second `agent` → `Agent 2`
- [ ] Duplicate named node → `{title} 2` (or next free n) + new id
- [ ] `GraphJsonImportTest` accepts a graph that includes `title`

**Tests**: phpunit `tests/GraphJsonImportTest.php` (title survives apply/validate); optional node script asserting `uniqueNodeTitle` if extracted from `graph.js`  
**Gate**: `./vendor/bin/phpunit tests/GraphJsonImportTest.php` + `npm run build`  
**Commit**: `feat(canvas): persist node title on graph save and drop`

---

### NN-T4 — Inspector header + canvas card

**What**: Dify header input in `NodeInspectorSidebar` (commit blur/Enter, empty/Escape revert, readOnly); card bold line uses `title` with type-label fallback; JS i18n keys.  
**Where**: `resources/js/studio-canvas/inspector/NodeInspectorSidebar.jsx`, `resources/js/studio-canvas/nodes/WorkflowNode.jsx`, `resources/js/i18n/en.json`, `resources/js/i18n/pt_BR.json`  
**Depends on**: NN-T3  
**Reuses**: `t()` from `resources/js/lib/i18n.js`, `NodeTypeIcon`  
**Requirement**: NN-02, NN-03, NN-09

**Done when**:

- [ ] Header is an input, not a static type label
- [ ] Empty blur does not write `""`
- [ ] Duplicate title on blur is rejected (revert or inline error) using the same uniqueness key as T3
- [ ] Card shows instance title; type slug unchanged
- [ ] `en` + `pt_BR` keys present for placeholder / duplicate name

**Tests**: none (React); verify via build + manual checklist in task notes  
**Gate**: `npm run build`  
**Commit**: `feat(canvas): edit node name in inspector header`

---

### NN-T5 — Codegen class/event from title [P]

**What**: `GraphTranspiler::nodeClassName` / `eventClassName` take node title; untitled keeps `studly(id)`; stub still emits `STUDIO_NODE_ID` = graph id.  
**Where**: `src/Codegen/GraphTranspiler.php`, call sites in the same class  
**Depends on**: NN-T1  
**Reuses**: `NodeTitle::slug`  
**Requirement**: NN-06

**Done when**:

- [ ] Titled `agent_1734` / `Qualificador de Lead` → `QualificadorDeLeadNode` + `QualificadorDeLeadEvent`
- [ ] Untitled `llm_1` still → `Llm1Node` / `Llm1Event` (existing `NativeWorkflowExporterTest` stays green)
- [ ] Generated node file contains `STUDIO_NODE_ID` = original id

**Tests**: `tests/NativeWorkflowExporterTest.php` (add titled-node case; do not break untitled fixtures)  
**Gate**: `./vendor/bin/phpunit tests/NativeWorkflowExporterTest.php`  
**Commit**: `feat(codegen): name native node classes from canvas title`

---

### NN-T6 — Snapshot `node_title` on steps/traces [P]

**What**: Record `node_title` on `__steps`; expose it on trace API; keep span `name` = `node_id`.  
**Where**: `src/Runtime/GraphExecutionLoop.php`, `src/Runtime/GraphStepExecutorNode.php` (if it also writes steps), `src/Http/Controllers/WorkflowTraceController.php`  
**Depends on**: NN-T1  
**Reuses**: graph node lookup already available in the loop/context  
**Requirement**: NN-07

**Done when**:

- [ ] Step array includes `node_title` (string or null)
- [ ] Trace JSON includes `node_title` when present
- [ ] Span `name` remains `node_id`

**Tests**: `tests/GraphExecutionLoopTest.php` and/or existing trace controller test — assert key present for titled node, null/absent-safe for untitled  
**Gate**: `./vendor/bin/phpunit tests/GraphExecutionLoopTest.php` (+ trace test file if touched)  
**Commit**: `feat(runtime): snapshot node_title on workflow steps`

---

### NN-T7 — Dock, thread, inspect, variable picker

**What**: Prefer `node_title` in live dock events, trace timeline, pretty thread `label`, inspect tree, and `getFlowNodeLabel`. Fallback to `node_id` / type label.  
**Where**: `resources/js/studio-canvas/chrome/BottomDock.jsx`, `resources/js/studio-canvas/.../TraceStepTimeline.jsx` (actual path), `resources/js/studio-chat/utils/workflowOutput.js`, inspect helper (`buildInspectTree.js`), `resources/js/studio-canvas/inspector/shared/stateVariables.js`  
**Depends on**: NN-T3, NN-T6  
**Reuses**: existing `label: nodeId` thread entries  
**Requirement**: NN-08

**Done when**:

- [ ] Pretty thread uses `node_title` when set (`WorkflowOutputJsTest` case)
- [ ] Untitled / missing title still labels with `node_id`
- [ ] Variable picker `sourceLabel` is the instance title
- [ ] Nested run prefix still works (`run_wf_1 › {title|id}`)

**Tests**: `tests/WorkflowOutputJsTest.php` (add titled + untitled cases)  
**Gate**: `./vendor/bin/phpunit tests/WorkflowOutputJsTest.php` + `npm run build`  
**Commit**: `feat(canvas): show node title in logs traces and pickers`

---

### NN-T8 — Docs

**What**: Document node name vs id, Dify empty-blur, uniqueness, codegen slug, no backfill.  
**Where**: `docs/guides/workflows/canvas-editor.md`; `docs/guides/export-and-production.md` only if it already documents class naming  
**Depends on**: NN-T2, NN-T4, NN-T5, NN-T7  
**Requirement**: NN-10

**Done when**:

- [ ] canvas-editor has a short “Node names” section
- [ ] Export docs mention slug-from-title vs `STUDIO_NODE_ID`

**Tests**: none  
**Gate**: none (docs path ignored by CI)  
**Commit**: `docs(canvas): describe editable node names`

---

## Parallel Execution Map

```
Phase 1:
  NN-T1

Phase 2 (after T1, parallel):
  ├── NN-T2 [P]  validator + lang
  ├── NN-T3 [P]  graph.js persist
  ├── NN-T5 [P]  codegen
  └── NN-T6 [P]  step snapshot

Phase 3:
  NN-T3 → NN-T4          inspector + card
  NN-T3 + NN-T6 → NN-T7  surfaces

Phase 4:
  NN-T2 + NN-T4 + NN-T5 + NN-T7 → NN-T8 docs
```

---

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| NN-T1 | 1 helper class | Granular |
| NN-T2 | GraphValidator + 2 lang files | Cohesive (one rule) |
| NN-T3 | graph persist + drop/duplicate | Cohesive (one data path) |
| NN-T4 | inspector + card + i18n keys | Cohesive (one UX) |
| NN-T5 | GraphTranspiler naming | Granular |
| NN-T6 | step + trace payload | Cohesive |
| NN-T7 | display consumers | Cohesive (one preference rule) |
| NN-T8 | docs | Granular |

---

## Diagram-Definition Cross-Check

| Task | Depends on (body) | Diagram | Status |
| ---- | ----------------- | ------- | ------ |
| NN-T1 | — | root | Match |
| NN-T2 | T1 | T1 → T2 | Match |
| NN-T3 | T1 | T1 → T3 | Match |
| NN-T4 | T3 | T3 → T4 | Match |
| NN-T5 | T1 | T1 → T5 | Match |
| NN-T6 | T1 | T1 → T6 | Match |
| NN-T7 | T3, T6 | T3→T7, T6→T7 | Match |
| NN-T8 | T2, T4, T5, T7 | those → T8 | Match |

T2/T3/T5/T6 do not depend on each other → `[P]` valid.

---

## Test Co-location Validation

| Task | Layer | Tests field | Status |
| ---- | ----- | ----------- | ------ |
| NN-T1 | PHP helper | unit phpunit | OK |
| NN-T2 | GraphValidator | unit phpunit | OK |
| NN-T3 | graph JSON + Livewire import | GraphJsonImportTest (+ optional node script) | OK |
| NN-T4 | React inspector/card | none (no JS unit runner); build gate | OK — same as EW-T4 / INV-T4 |
| NN-T5 | Codegen | NativeWorkflowExporterTest | OK |
| NN-T6 | Runtime steps / trace | GraphExecutionLoop (+ trace if touched) | OK |
| NN-T7 | workflowOutput.js + consumers | WorkflowOutputJsTest | OK |
| NN-T8 | docs | none | OK |

---

## Traceability

| Requirement | Tasks |
| ----------- | ----- |
| NN-01 | NN-T3 |
| NN-02 | NN-T4 |
| NN-03 | NN-T4 |
| NN-04 | NN-T1, NN-T3 |
| NN-05 | NN-T1, NN-T2 |
| NN-06 | NN-T1, NN-T5 |
| NN-07 | NN-T6 |
| NN-08 | NN-T7 |
| NN-09 | NN-T2, NN-T4 |
| NN-10 | NN-T8 |
| NN-11 | — (P2, not scheduled) |

**Coverage:** 10 P1 IDs mapped; NN-11 deferred.

---

## Notes for Execute

- Do not mutate `id`. Do not backfill titles on load.
- Do not put `title` inside type `data` / `config`.
- Keep existing untitled exporter fixtures (`Llm1Event`) green.
- Feature is independent of M19/M20; same line `v3.1.x`.
- Atomic conventional commits per task; PR back to `v3.1.x`.
