# Optional Multi-Tenancy Specification

**Requirement IDs:** `TEN-xx` · **Date:** 2026-08-18  
**Context:** [context.md](./context.md) · **Design:** [design.md](./design.md) · **Tasks:** [tasks.md](./tasks.md)  
**Line:** `v3.1.x` · **Milestone:** M18

## Problem Statement

NeuronAI Studio is app-scoped: agents, workflows, knowledge bases, variables, threads, runs, and usage live in one silo. Host SaaS apps cannot isolate authoring or runtime per organization without forking queries. Global Variables and usage export explicitly deferred tenant columns. Hosts that already use stancl/tenancy (database-per-tenant) need the package not to fight that isolation.

## Goals

- [x] Tenancy is opt-in (`enabled` default false); existing hosts keep current behavior and the current test suite stays green
- [x] Shared-DB hosts isolate authoring + runtime with nullable `tenant_id` + host `TenantResolver`
- [x] HTTP Studio / integrate / MCP / usage abort 403 when tenancy is on and no tenant is resolved
- [x] Global catalog (`tenant_id` null) remains visible as fallback; tenant rows override by slug/name
- [x] Stancl database-per-tenant works without Composer dependency (driver `database`, no `tenant_id` scope)
- [x] Thread owner (`ownerable_*`) stays orthogonal to tenant

## Out of Scope

| Feature | Reason |
|---------|--------|
| Tenant CRUD / switcher / impersonation in Studio UI | Host owns organizations |
| Cross-database global catalog (Stancl DB-per-tenant) | Templates install inside each tenant DB |
| Billing providers / advanced per-tenant BI | Usage already filters via Eloquent scope |
| Changing `workflow_definitions.class_path` uniqueness | Maps to a single PHP file on disk |
| Global badge on Index tables | P2 polish |

## User Stories

### P1: Opt-in shared-database isolation ⭐ MVP

**User Story**: As a host developer, I want to enable Studio tenancy with a resolver class so each organization only sees and writes its own Studio data, without breaking single-tenant installs.

**Acceptance Criteria**:

1. WHEN `neuronai-studio.tenancy.enabled` is false THEN Eloquent SHALL NOT filter by `tenant_id` and writes SHALL leave `tenant_id` null.
2. WHEN enabled, driver `shared`, and resolver returns `acme` THEN list/read SHALL include rows with `tenant_id = acme` OR `tenant_id` null, and SHALL exclude other tenants.
3. WHEN a tenant HTTP request creates an authoring or runtime row THEN system SHALL stamp `tenant_id` to the current tenant (not null).
4. WHEN tenancy is enabled and the resolver returns null on Studio, integrate, MCP, or usage HTTP THEN system SHALL abort 403.

**Independent Test**: PHPUnit with a fake resolver: tenant A cannot load tenant B's agent; default-off suite unchanged.

### P1: Tenant slug override of globals ⭐ MVP

**User Story**: As a platform operator, I want a global `support-bot` agent plus a tenant-specific `support-bot` so the tenant's definition wins without renaming.

**Acceptance Criteria**:

1. WHEN uniqueness is applied THEN slug (or variable `name`) SHALL be unique per `(tenant_scope, identifier)` where global scope is empty string, not globally unique across tenants.
2. WHEN current tenant has a row with slug X THEN find-by-slug SHALL return that row even if a global X exists.
3. WHEN current tenant has no row with slug X and a global X exists THEN find-by-slug SHALL return the global.
4. WHEN two tenants use the same slug THEN neither SHALL see the other's row.

**Independent Test**: Create global + tenant-a + tenant-b same slug; assert finder results per context.

### P1: Queue and RAG stay in tenant ⭐ MVP

**User Story**: As a host, I want async workflow runs and KB ingest to keep using the tenant that owned the record, even on a worker with no HTTP tenant.

**Acceptance Criteria**:

1. WHEN `RunWorkflowJob` / `ResumeWorkflowJob` / `IngestKnowledgeDocumentJob` run THEN they SHALL restore tenant from the persisted run or knowledge base before querying Studio models.
2. WHEN driver is `shared` and a tenant-owned KB is used THEN vector store collection/index/file name SHALL be prefixed with a sanitized tenant id so tenants do not collide.
3. WHEN the KB is global (`tenant_id` null) THEN the store name SHALL NOT be prefixed.

**Independent Test**: Job handle under empty resolver still finds the tenant workflow; factory namespacing unit test.

### P2: Stancl database driver

**User Story**: As a host on stancl/tenancy with a database per tenant, I want Studio tables to live in the tenant DB without a redundant `tenant_id` global scope.

**Acceptance Criteria**:

1. WHEN `tenancy.driver` is `database` THEN `BelongsToTenant` SHALL NOT apply the `tenant_id` visibility scope.
2. WHEN HTTP has no resolved tenant THEN middleware SHALL still 403 (mount Studio on tenant domains, not central).
3. WHEN docs are followed THEN host SHALL add Studio migrations to the tenant migration path; optional `StanclTenantResolver` uses `tenant()` without requiring stancl in composer.json.

**Independent Test**: With driver `database` and two `tenant_id` values in one SQLite, queries return both (no scope).

---

## Edge Cases

- WHEN tenancy is enabled and Eloquent `creating` runs with no tenant and not `StudioTenancy::central()` THEN system SHALL throw (fail-closed writes).
- WHEN `StudioTenancy::central()` runs THEN queries SHALL see only `tenant_id` null and writes MAY create globals.
- WHEN a job's root row has `tenant_id` null THEN handle SHALL run in central context.
- WHEN `class_path` collides across tenants THEN the existing global unique on `class_path` SHALL still reject the second row.
- WHEN tenancy is disabled THEN unique `(tenant_scope, slug)` still enforces one global slug because all `tenant_scope` values are `''`.

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| TEN-01 | P1: Opt-in | Execute | Verified |
| TEN-02 | P1: Opt-in | Execute | Verified |
| TEN-03 | P1: Opt-in | Execute | Verified |
| TEN-04 | P1: Override | Execute | Verified |
| TEN-05 | P1: Queue/RAG | Execute | Verified |
| TEN-06 | P2: Stancl | Execute | Verified |
| TEN-07 | P1: Tests + docs | Execute | Verified |

**ID format:** `TEN-[NUMBER]`

**Coverage:** 7 total

---

## Success Criteria

- [ ] Default-off: existing PHPUnit suite green without fixture changes
- [ ] Enabled shared: tenant isolation + slug override + HTTP 403 + job restore proven in new tests
- [ ] Docs: `docs/guides/tenancy.md` + config/schema pointers
- [ ] No stancl Composer dependency
