# Thread–Owner Association Specification

**Requirement IDs:** `TO-xx` · **Date:** 2026-08-07  
**Context:** [context.md](./context.md)

## Problem Statement

Studio threads are anonymous UUIDs owned only by agent/workflow entity. Host apps cannot register conversations to a customer/user model, list threads per owner, or stamp owner into workflow state for tools — blocking future cross-thread memory.

## Goals

- [x] Persist polymorphic owner on `StudioThread`
- [x] Stamp / hydrate `__studio_owner_type` + `__studio_owner_id` in workflow state
- [x] Expose owner keys in `SYSTEM_STATE_VARIABLES`
- [x] `StudioInvoke::forOwner` + payload `owner_type`/`owner_id`
- [x] `ChatThreadIndex::listForOwner`
- [x] Immutable ownership on mismatch

## Out of Scope

| Feature | Reason |
|---------|--------|
| Cross-thread summary / RAG | Deferred TO-D1/D2 |
| Studio UI multi-thread by owner | Deferred TO-D3 |
| Owner on each chat_message | Inherit via thread |
| Opaque string without Model | Host wraps Contact/etc. |

## Requirements

### TO-01 Schema
Migration adds nullable `ownerable_type` + `ownerable_id` (string) + composite index.

### TO-02 Model
`StudioThread::owner(): MorphTo`; fillable; scopes.

### TO-03 Runtime bind
Agent/Workflow session create binds owner; mismatch throws; empty assigns.

### TO-04 State
`buildInitialState` / native state include owner keys from input or thread.

### TO-05 Picker + docs
`SYSTEM_STATE_VARIABLES` + state-and-conditions + invoke docs.

### TO-06 List API
`listForOwner(Model|type+id)` with optional entity filter.

### TO-07 Observability
Langfuse `user_id` from thread `ownerable_id` when meta omits it.

### TO-08 Tests
Assign, match, reject, hydrate, list, playground null.

### TO-09 Builder
`StudioInvoke::workflow|agent()->forOwner()->onThread()->run|stream`.
