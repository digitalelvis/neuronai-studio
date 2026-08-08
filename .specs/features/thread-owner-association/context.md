# Thread–Owner Association Context

**Gathered:** 2026-08-07  
**Spec:** `.specs/features/thread-owner-association/spec.md`  
**Status:** Ready for implement

---

## Feature Boundary

First-class polymorphic ownership on `StudioThread` (`ownerable_*`), state keys `__studio_owner_type` / `__studio_owner_id`, invoke DX via `StudioInvoke::…->forOwner($model)`, and `ChatThreadIndex::listForOwner`. No cross-thread memory/RAG in V1.

---

## Decisions

| Area | Decision |
|------|----------|
| Identity | Eloquent morph (`ownerable_type` + `ownerable_id` as string) — not opaque string-only |
| Method name | `forOwner($model)` on `StudioInvoke` builder |
| Conflict | Immutable: mismatch → exception; null → assign; same → ok |
| State hydrate | Prefer payload owner; else hydrate from thread |
| Messages | No owner columns on `chat_messages` |
| Deferred | Cross-thread summary, conversation RAG, Studio multi-thread UI |

---

## Deferred Ideas

- TO-D1: Summarize other threads for same owner into prompt
- TO-D2: RAG over prior conversations for same owner
- TO-D3: Studio UI listing threads by owner
