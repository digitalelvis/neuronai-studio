# Shared Plugin Packages — Context

**Gathered:** 2026-09-09  
**Spec:** `.specs/features/shared-plugin-packages/spec.md`  
**Status:** Ready for design  
**Source:** Agent discretion — mature/solid path (user requested)

---

## Feature Boundary

Catalog plugins share skill content once per `(slug, version)`. Tenant installs stay thin (pin + accounts + bindings). Custom studio/upload/github skills remain tenant-owned on `skill_definitions`. Closed catalog and host policy unchanged. GitHub catalog sources and M22 clone migration are P2.

---

## Implementation Decisions

### 1. Shared content storage

- **Choice: DB tables** (`plugin_packages` + `plugin_package_skills` via StudioTables).
- Rationale: multi-instance safe (no shared FS), transactional with install, backups with the app DB, fits existing Eloquent/tenancy patterns. Disk cache deferred if payloads grow.

### 2. MCP per tenant

- **Choice: keep tenant `mcp_servers` rows**, populated from package `mcp_templates` at install (same credential/`var:` / `PluginMcpGate` path as M22).
- Rationale: MCP config is small; skill bodies are the scale problem. Avoids rewriting binder/gate/credentials in P1. Package remains source of truth for templates; install copies into tenant overlay.
- Deferred: true shared MCP template + overlay-only row (P3 if row count becomes an issue).

### 3. M22 migration

- **Choice: P2** — P1 changes new catalog installs only; existing `source=plugin` skill clones untouched until migration ships.
- Rationale: safer rollout; demo/greenfield validates model before rewriting production data.

### 4. Official packs location

- **Choice: keep local `resources/plugins/*` for P1**; GitHub `source.type=github` in catalog = P2.
- Rationale: offline/demo/CI without network; pin GitHub after package store is proven.
- `demo-assistant` always local.

### Agent's Discretion (locked here)

- Skill ref scheme: `skill:pkg:{packageId}:{skillSlug}` (stable id, not only slug@version).
- Package identity: `UNIQUE(slug, version)` plus `content_hash` for integrity.
- Extract a small `SkillContent` contract so `SkillCatalogEntry` / activate tools work for both `SkillDefinition` and package skills without synthetic Eloquent hacks.
- Allowlist zip / ad-hoc GitHub install: **tenant-owned** materialization (unchanged M22 path) — only **catalog** installs use the shared package store.
- Single-tenant hosts still use the global package tables (no special case).

---

## Specific References

- Scale scenario: 5 000 tenants × 15 skills must not create 75 000 skill bodies.
- Reuse: `PluginInstaller`, `PluginManifestParser`, `PluginAgentBinder`, `PluginMcpGate`, `SkillRepository` / `SkillResolver`.
- Cursor marketplace GitHub pins are a future catalog feed (P2), not P1 UX.

---

## Deferred Ideas

- Content-addressed dedupe across different slugs with identical bodies
- Auto-upgrade / channel (latest) for packages
- Shared FS or object-storage blob backend for large `resources`
- Cursor marketplace crawler as Studio admin tool
- Collapsing tenant MCP rows into overlay-only storage
