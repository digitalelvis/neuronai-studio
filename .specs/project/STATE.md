# State

**Last Updated:** 2026-06-24
**Current Work:** workflow-json-io + workflow-code-bridge — complete

---

## Recent Decisions (Last 60 days)

### AD-001: IIFE output for studio JS bundles (2026-06-24)

**Decision:** Build `workflow-canvas.bundle.js` and `studio-chat.bundle.js` as IIFE (`NeuronAIStudioCanvas`, `NeuronAIStudioChat`).
**Reason:** Both bundles ship React with overlapping minified top-level `const` names; loading both on workflow editor caused `Identifier 'fo' has already been declared`.
**Trade-off:** Slightly larger bundles; CSS now injected via JS instead of separate `.css` files from Vite.
**Impact:** Workflow editor loads canvas + chat without global scope collision; `window.mountStudioChat` available for Test tab.

### AD-002: POST SSE for workflow runs and human resume (2026-06-24)

**Decision:** Workflow test harness uses POST stream endpoints with checkpoint/resume for Human nodes.
**Reason:** Supports attachments, context payload, and conversational resume without modals.
**Trade-off:** Breaking change from GET workflow run stream.
**Impact:** `HumanNodeExecutor` throws `HumanInputRequiredException`; `WorkflowRunner` persists checkpoint with `awaiting_input` status.

---

## Active Blockers

_None._

---

## Lessons Learned

### L-001: Multiple Vite bundles need isolated scope (2026-06-24)

**Context:** Workflow editor loads two production bundles on same page.
**Problem:** Default Vite output leaked shared minified identifiers into global lexical scope → SyntaxError on page load.
**Solution:** `format: 'iife'` per bundle in `vite.config.js`.
**Prevents:** Duplicate identifier errors when adding more studio bundles to same layout.

---

## Features Completed

| Feature              | Date       | Commit | Status  |
| -------------------- | ---------- | ------ | ------- |
| studio-test-harness  | 2026-06-24 | f8a29d2 | ✅ Done |
| workflow-json-io     | 2026-06-24 | —       | ✅ Done |
| workflow-code-bridge | 2026-06-24 | —       | ✅ Done |

---

## Deferred Ideas

- [ ] Remove redundant layout `<link>` tags for `studio-chat.css` / `workflow-canvas.css` now that styles are inlined in bundles — Captured during: studio-test-harness
- [ ] Extract `StudioTestHarness.jsx` shell component (design doc) if Playground+Chat composition grows — Captured during: studio-test-harness

---

## Todos

- [ ] Republish assets in consuming apps: `php artisan vendor:publish --tag=neuronai-studio-assets --force`
- [ ] Republish views if layout changed: `php artisan vendor:publish --tag=neuronai-studio-views --force`
