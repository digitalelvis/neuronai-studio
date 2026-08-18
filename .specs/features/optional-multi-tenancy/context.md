# Optional Multi-Tenancy Context

**Gathered:** 2026-08-18  
**Spec:** `.specs/features/optional-multi-tenancy/spec.md`  
**Status:** Ready for design

---

## Feature Boundary

Optional multi-tenancy for NeuronAI Studio as a Laravel package: default off; shared-database `tenant_id` plus host `TenantResolver`; authoring and runtime isolated; global catalog with tenant slug override; HTTP fail-closed (403); Stancl compatibility without a Composer dependency. No tenant CRUD in Studio. Thread owner stays a separate concept.

---

## Implementation Decisions

### Isolation model

- Native path: shared DB + nullable `tenant_id` (opt-in).
- Also document/compat with stancl/tenancy (`driver=database` skips `tenant_id` scope; migrations in tenant path).

### Surfaces

- Authoring and runtime: agents, workflows, KBs, tools, MCP servers/endpoints, variables, evals (via parent), threads, runs, traces, usage.

### Tenant resolution

- Host `TenantResolver::id(): ?string` only. Studio does not read headers or subdomains.
- No Studio UI for registering or switching tenants.

### Shared resources

- Global rows (`tenant_id` null) visible to every tenant as fallback.
- Tenant rows are a silo; tenant B never sees tenant A.
- HTTP writes always stamp the current tenant. Globals are created via tenancy off, seeders, `neuronai-studio:install`, or `StudioTenancy::central()`.

### Lookup

- Agent discretion: tenant row overrides global by slug/name. Unique per `(tenant_scope, identifier)` with `tenant_scope = COALESCE(tenant_id, '')`.

### Missing tenant

- Abort 403 on any Studio / integrate / MCP / usage HTTP request when `tenancy.enabled=true` and resolver returns null.

### Agent's Discretion

- Portable unique key: denormalized `tenant_scope` (not a generated column) kept in sync on save — SQLite Testbench friendly.
- Vector store prefix only for name-like identifiers (collection, index, file name, namespace), not full URLs.
- Global Index badge deferred (P2).
- `StanclTenantResolver` uses `function_exists('tenant')` + `getTenantKey()`.

---

## Specific References

Thread owner association (`ownerable_*` / `StudioInvoke::forOwner`) is **not** tenancy. Variable vault ADR-028 left a repository seam for `tenant_id`. Usage export treated multi-tenant isolation as a host concern until this feature.

---

## Deferred Ideas

- Studio UI badge “Global” on Index tables
- Cross-database platform catalog for Stancl DB-per-tenant
- Tenant impersonation / super-admin “all tenants” Studio
- Billing providers keyed by tenant
