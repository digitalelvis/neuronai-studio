# Optional Multi-Tenancy Tasks

**Design**: `.specs/features/optional-multi-tenancy/design.md`  
**Status**: Done

---

## Execution Plan

### Phase 1: Foundation

```
T1 → T2 → T3 → T4
```

### Phase 2: Apply + lookup

```
T4 → T5 → T6 → T7
```

### Phase 3: Jobs, RAG, tests, docs

```
T7 → T8 → T9 → T10
```

---

## Task Breakdown

### T1: Config, contracts, StudioTenancy

**What**: Add `tenancy` config; `TenantResolver`, `NullTenantResolver`, `StanclTenantResolver`, `StudioTenancy`, `TenantRequiredException`.
**Where**: `config/neuronai-studio.php`, `src/Tenancy/*`
**Depends on**: None
**Requirement**: TEN-01, TEN-06
**Done when**:
- [ ] `enabled` default false, `driver` shared, `resolver` null
- [ ] `StudioTenancy::run` / `central` nest and restore
**Tests**: unit (with T10)
**Gate**: full

### T2: TenantScope + BelongsToTenant + middleware

**What**: Global scope states; stamp/scope helpers; `EnsureStudioTenant`; bind resolver; alias middleware; append on all route files.
**Where**: `src/Tenancy/`, `src/Http/Middleware/EnsureStudioTenant.php`, `src/NeuronAIStudioServiceProvider.php`, `routes/*.php`
**Depends on**: T1
**Requirement**: TEN-02, TEN-03
**Done when**:
- [ ] 403 when enabled and no tenant
- [ ] no-op when disabled
**Tests**: unit/http (T10)
**Gate**: full

### T3: Migration tenant_id + unique composite

**What**: New migration adding `tenant_id`/`tenant_scope`, drop slug/name uniques, short unique names.
**Where**: `database/migrations/2026_08_18_000026_add_tenant_id_to_studio_tables.php`
**Depends on**: T1
**Requirement**: TEN-04
**Done when**:
- [ ] Tenantable tables listed in design have columns
- [ ] `class_path` unique untouched
**Tests**: MigrationTest extended in T10
**Gate**: full

### T4: Attach trait to tenantable models

**What**: Use `BelongsToTenant` on the ten models; keep existing `booted()` hooks.
**Where**: `src/Models/{AgentDefinition,WorkflowDefinition,KnowledgeBase,ToolDefinition,McpServer,McpEndpoint,Variable,StudioThread,StudioRun,StudioTrace}.php`
**Depends on**: T2, T3
**Requirement**: TEN-02
**Done when**:
- [ ] Creating in tenant context stamps `tenant_id`
**Tests**: T10
**Gate**: full

### T5: Finder by slug/name

**What**: `findBySlug` / `findPreferred`; wire AgentRunner, MCP catalog/auth, VariableRepository, TemplateInstaller, Workflows Index/Editor uniqueness via `inCurrentTenant`.
**Where**: listed call sites in spec
**Depends on**: T4
**Requirement**: TEN-04
**Done when**:
- [ ] Tenant overrides global; uniqueness allows same slug across tenants
**Tests**: T10
**Gate**: full

### T6: Jobs restore tenant

**What**: `RestoresStudioTenant` on Run/Resume/Ingest jobs.
**Where**: `src/Jobs/*.php`, `src/Tenancy/RestoresStudioTenant.php`
**Depends on**: T4
**Requirement**: TEN-05
**Done when**:
- [ ] Worker with null resolver still loads tenant-owned run/workflow/KB
**Tests**: T10
**Gate**: full

### T7: RAG collection prefix

**What**: `VectorStoreFactory::namespaced()` on name-like identifiers.
**Where**: `src/Runtime/Rag/VectorStoreFactory.php`
**Depends on**: T4
**Requirement**: TEN-05
**Done when**:
- [ ] Tenant KB file/chroma/pinecone names prefixed; global KB not
**Tests**: T10
**Gate**: full

### T8: Docs

**What**: `docs/guides/tenancy.md`; pointers from README, configuration, database-schema, knowledge-bases vector stores, security.
**Where**: `docs/`
**Depends on**: T1
**Requirement**: TEN-07
**Done when**:
- [ ] Host can enable shared + Stancl from the guide
**Tests**: none
**Gate**: none

### T9: ROADMAP / STATE

**What**: AD-036, M18, deferred usage attribution note.
**Where**: `.specs/project/STATE.md`, `.specs/project/ROADMAP.md`
**Depends on**: T1
**Requirement**: TEN-07
**Done when**:
- [ ] M18 listed; Current Work points at this feature
**Tests**: none
**Gate**: none

### T10: Isolation tests

**What**: PHPUnit coverage for default-off, isolation, override, 403, jobs, database driver, namespacing.
**Where**: `tests/Tenancy/*`, `tests/MigrationTest.php`, `tests/Support/MutableTenantResolver.php`
**Depends on**: T5, T6, T7
**Requirement**: TEN-07
**Done when**:
- [ ] New tests pass; existing suite still green
**Tests**: unit/integration
**Gate**: full
