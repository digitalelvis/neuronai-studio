# Shared Plugin Packages — Specification

**Requirement IDs:** `SPP-xx` · **Date:** 2026-09-09  
**Milestone:** M23 · **Line:** `v3.1.x`  
**Depends on:** M22 `plugin-system` (AD-038), M21 `agent-skills`  
**Context:** [context.md](./context.md) · **Design:** [design.md](./design.md) · **Tasks:** pending

## Problem Statement

M22 materializes each plugin install into **tenant-owned** `SkillDefinition` and `McpServer` rows that copy full skill bodies and MCP configs. On a SaaS host with thousands of tenants, the same official pack (e.g. 15 skills) is duplicated per install — storage and update cost grow linearly with tenants, not with distinct packages. Skills custom (upload/studio) must stay tenant-owned; **catalog plugins** must not.

## Goals

- [ ] One shared package definition per `(slug, version)` for catalog plugins — skill content stored once.
- [ ] Tenant install is a thin record: package pin + accounts/credentials + agent bindings.
- [ ] Runtime resolves plugin skills from the shared package (not per-tenant `skill:db:` clones).
- [ ] Catalog entries may reference GitHub (`url` + `ref` + `path`) without shipping pack files in the Composer package.
- [ ] Custom tenant skills (studio/upload/github personal) remain unchanged on `skill_definitions`.

## Out of Scope

| Feature | Reason |
|---------|--------|
| Open / public marketplace | Still closed catalog (AD-038) |
| Per-tenant fork/edit of catalog skill bodies | Would reintroduce N× copies; custom skills cover that |
| Automatic silent upgrade of installed package version | Explicit pin; upgrade is a separate story (P2) |
| Cursor marketplace crawler as product surface | Optional internal tooling; not Studio UX |
| Changing OAuth / vault credential model | Already tenant-scoped; keep as-is |
| `scripts/` execution in plugin skills | M21 deferral |

---

## User Stories

### P1: Shared package store ⭐ MVP

**User Story**: As a host operator, I want catalog plugin skills stored once globally so that 5 000 tenants installing the same pack do not create 5 000× skill rows.

**Why P1**: Core scale constraint for SaaS; without this, GitHub catalog sources make the duplication worse.

**Acceptance Criteria**:

1. WHEN a catalog plugin is first resolved (local path or GitHub) THEN system SHALL upsert a global `plugin_packages` (or equivalent) row keyed by `slug` + `version` (or content hash) containing parsed manifest metadata.
2. WHEN the same package version is installed by a second tenant THEN system SHALL NOT create additional skill body rows for that version.
3. WHEN package skills are stored THEN each skill body/resources SHALL exist at most once per package version.
4. WHEN `tenant_id` tenancy is enabled THEN shared packages SHALL be visible to all tenants (global / central scope); installs remain tenant-scoped.

**Independent Test**: Install Linear for tenant A and tenant B; assert skill content row count for that package version is 1, install rows are 2.

---

### P1: Thin tenant install ⭐ MVP

**User Story**: As a tenant author, I want installing a catalog plugin to create only install + account state so that my workspace stays light while skills still appear on agents.

**Why P1**: Preserves M22 UX (Add → credentials → bind) without materializing heavy content.

**Acceptance Criteria**:

1. WHEN author installs from catalog THEN system SHALL create/update `plugin_installs` with package identity (`slug` + `version`/`package_id`) and status `installed`, without cloning skill bodies into tenant `skill_definitions`.
2. WHEN install completes THEN system SHALL still create default `plugin_accounts` (`needs_auth`) and credential hints as today.
3. WHEN uninstall THEN system SHALL remove install, accounts, agent bindings, and **tenant-owned** MCP rows tied to that install; SHALL NOT delete the shared package or its skills.
4. WHEN install references a package that is not yet cached THEN system SHALL fetch/parse once (local or GitHub) before creating the install.

**Independent Test**: Install → uninstall on one tenant; shared package remains; other tenant’s install of same package still works.

---

### P1: Runtime skill resolution ⭐ MVP

**User Story**: As an agent bound to a plugin, I want `activate_skill` to load instructions from the shared package so that behavior matches the catalog pack without tenant copies.

**Why P1**: Without runtime resolution, thin installs are useless.

**Acceptance Criteria**:

1. WHEN agent is bound to a plugin install THEN binder SHALL attach skill refs that resolve to the shared package (e.g. `skill:plugin:{slug}/{skill}` or `skill:package:{id}:{skill}`), not new `skill:db:{tenant_id}` rows.
2. WHEN runtime activates a plugin skill ref THEN system SHALL load body/resources from the shared package store.
3. WHEN account is `needs_auth` THEN MCP gate SHALL still skip plugin MCP tools (unchanged from PLG-07).
4. WHEN agent also has custom `skill:db:` skills THEN those SHALL continue to resolve from tenant `skill_definitions`.

**Independent Test**: Bind Linear on agent; playground activate skill returns Linear SKILL body; no tenant skill_definitions row for `linear`.

---

### P1: MCP without N× config clones (minimal) ⭐ MVP

**User Story**: As a host, I want MCP connector config for catalog plugins not duplicated as full independent definitions per tenant beyond what credentials require.

**Why P1**: MCP rows today also scale with tenants; credentials must stay per-tenant.

**Acceptance Criteria**:

1. WHEN a catalog plugin defines an HTTP MCP server THEN install SHALL ensure a tenant-scoped MCP binding that applies credential map (`var:…`) without requiring a full duplicate of immutable fields if a shared template exists — OR, if a per-tenant `mcp_servers` row remains for isolation simplicity, it SHALL store only override/credential wiring and reference the package connector key (document chosen approach in design).
2. WHEN two tenants install the same plugin THEN each SHALL have isolated credentials and auth status.
3. WHEN `plugins.stdio` is false THEN stdio connectors SHALL still be rejected at install (PLG-01).

**Independent Test**: Two tenants install Stripe with different vault vars; each agent only uses its own credentials.

**Note**: Exact storage shape (shared MCP template + tenant overlay vs slim per-tenant row) is a design decision locked in Discuss/Design; MVP success = no skill-body duplication + credential isolation.

---

### P2: GitHub catalog sources

**User Story**: As a host, I want marketplace entries to point at GitHub `url`/`ref`/`path` so that I do not vendor connector packs inside the Composer package.

**Why P2**: Follows shared packages; local path catalog can remain for demo/offline.

**Acceptance Criteria**:

1. WHEN catalog entry has `source.type = github` THEN listing SHALL appear in closed catalog without a local directory on disk.
2. WHEN author installs that listing THEN system SHALL download the pinned ref (reuse `PluginInstaller::installFromGitHub` / zipball), upsert shared package, then create thin install.
3. WHEN `ref` is a commit SHA THEN system SHALL pin that content for the package version.
4. WHEN download fails THEN install SHALL fail with a clear error; no partial tenant install.

**Independent Test**: Catalog-only JSON entry for Linear GitHub URL installs successfully with package cached once.

---

### P2: Migrate existing materialized plugin skills

**User Story**: As a host upgrading from M22, I want existing plugin-sourced `skill_definitions` collapsed into shared packages so that storage stops growing.

**Why P2**: Needed for production hosts already on M22 materialization; can ship after MVP if greenfield demo is enough.

**Acceptance Criteria**:

1. WHEN migration runs THEN skills with `source = plugin` SHALL be grouped by plugin slug/version into shared packages.
2. WHEN migration completes THEN `plugin_installs.materialized.skill_ids` SHALL be rewritten to package skill refs (or cleared in favor of package linkage).
3. WHEN duplicate identical bodies exist across tenants THEN migration SHALL keep one shared copy.
4. WHEN a skill cannot be attributed to a package THEN system SHALL leave it as tenant-owned and log/skip safely.

**Independent Test**: Seed two tenants with cloned Linear skills → migrate → one shared package, two installs, agents still resolve skill.

---

### P3: Explicit package upgrade

**User Story**: As a tenant author, I want to upgrade an installed plugin to a newer pinned version when the host publishes one.

**Acceptance Criteria**:

1. WHEN a newer package version exists in catalog THEN UI/API MAY offer upgrade.
2. WHEN author upgrades THEN install pin updates; skill refs follow new package version; credentials preserved when env keys unchanged.

---

## Edge Cases

- WHEN catalog local path and GitHub source both define the same slug THEN host config / package catalog precedence SHALL be deterministic (document in design).
- WHEN package fetch returns a pack with zero skills and only MCP THEN install SHALL succeed (MCP-only connectors).
- WHEN package fetch returns skills but MCP fails policy (stdio) THEN install SHALL fail atomically.
- WHEN shared package row exists but skill blob missing/corrupt THEN activate_skill SHALL return a clear error (not empty success).
- WHEN single-tenant host (`tenancy.enabled = false`) THEN shared package store SHALL still be used (global scope); behavior identical, scale less critical.
- WHEN author uploads a zip plugin in `allowlist` mode THEN content MAY remain tenant-owned (not forced into global share) unless host opts into sharing — default: tenant-owned for non-catalog sources.

---

## Requirement Traceability

| ID | Story | Phase | Status |
|----|-------|-------|--------|
| SPP-01 | P1: Shared package store | Execute | Verified |
| SPP-02 | P1: Thin tenant install | Execute | Verified |
| SPP-03 | P1: Runtime skill resolution | Execute | Verified |
| SPP-04 | P1: MCP credential isolation without skill clones | Execute | Verified |
| SPP-05 | P1: Uninstall does not delete shared package | Execute | Verified |
| SPP-06 | P2: GitHub catalog source type | - | Pending |
| SPP-07 | P2: Migration from M22 clones | - | Pending |
| SPP-08 | P3: Explicit package upgrade | - | Pending |
| SPP-09 | Edge: catalog source precedence | Execute | Verified |
| SPP-10 | Edge: allowlist zip stays tenant-owned by default | Execute | Verified |

**Coverage:** 10 total, 0 mapped to tasks, 10 unmapped

---

## Success Criteria

- [ ] 5 000 simulated installs of a 15-skill pack create **≤ 15** shared skill content rows (plus thin installs), not 75 000.
- [ ] Agent bind + activate_skill works for catalog plugins without tenant `skill_definitions` rows for those skills.
- [ ] Custom studio/upload skills unchanged.
- [ ] Closed catalog + host policy (enabled/mode/stdio) preserved.
- [ ] Existing M22 flows (credentials, needs_auth gate, agent plugin UI) keep working.

## Relationship to M22

| M22 (AD-038) | M23 change |
|--------------|------------|
| Materialize skills into tenant `skill_definitions` | Stop for catalog plugins; shared package store |
| `materialized.skill_ids` → db ids | Package skill refs |
| Local catalog paths only in P1 | P2: GitHub sources in catalog JSON |
| Closed catalog | Unchanged |
