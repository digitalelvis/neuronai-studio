# Plugin System — Specification

**Requirement IDs:** `PLG-xx` · **Date:** 2026-09-08  
**Milestone:** M22 · **Line:** `v3.1.x`  
**Context:** [context.md](./context.md) · **Design:** [design.md](./design.md) · **Tasks:** [tasks.md](./tasks.md)

## Problem Statement

Integrations (MCP + skills + credentials) are wired across disconnected Studio screens. Hosts need a **closed catalog** of official connector packs (Claude-compatible manifests) with multi-account support and a host kill-switch — not an open third-party marketplace.

## Goals

- [ ] Host-controlled plugin catalog (`closed` default, optional `allowlist`).
- [ ] Install Claude-format packs (manifest + skills + MCP) into tenant-scoped materialized records.
- [ ] Plugin accounts with vault-backed credentials and `needs_auth` / `connected` status.
- [ ] Bind installed plugins on agents (skills + MCP pivot sync).
- [ ] Runtime skips plugin MCP tools when account is not authenticated.

## Out of Scope

| Feature | Reason |
|---------|--------|
| Open marketplace / public registry | Host persona: closed catalog only |
| OAuth flows | Host adapter; P3 |
| Share, ratings, social UI | Not product value for embedded Studio |
| PHP / Neuron Tool classes in plugins | Security |
| Claude hooks, subagents, LSP | Not Neuron runtime |
| `scripts/` execution in plugin skills | M21 security deferral |

---

## User Stories

### P1: Host policy ⭐ MVP

**Acceptance Criteria:**

1. WHEN `plugins.enabled` is false THEN Studio SHALL hide plugin routes and reject install.
2. WHEN `plugins.mode` is `closed` THEN install SHALL only accept catalog listings from package + host config.
3. WHEN `plugins.mode` is `allowlist` THEN install SHALL also accept slugs/URLs in host allowlist.
4. WHEN install source is not permitted THEN system SHALL return a clear error.

### P1: Install pack ⭐ MVP

**Acceptance Criteria:**

1. WHEN author installs from catalog path or zip THEN system SHALL parse `.claude-plugin/plugin.json` and materialize skills + MCP servers.
2. WHEN pack includes stdio MCP AND `plugins.stdio` is false THEN install SHALL fail unless command is allowlisted.
3. WHEN install completes THEN system SHALL create `plugin_installs` + default `plugin_accounts` row (`default`, `needs_auth`).
4. WHEN uninstall THEN system SHALL remove materialized plugin-owned skills/MCP not referenced elsewhere.

### P1: Accounts ⭐ MVP

**Acceptance Criteria:**

1. WHEN author maps env keys to `var:NAME` and all resolve THEN account status SHALL be `connected`.
2. WHEN any required credential is empty THEN status SHALL be `needs_auth`.
3. WHEN agent uses plugin MCP and account is `needs_auth` THEN runtime SHALL NOT expose those MCP tools.

### P1: Agent binding ⭐ MVP

**Acceptance Criteria:**

1. WHEN author selects an installed plugin on Agent form THEN system SHALL sync agent skills refs + MCP pivot from the install.
2. WHEN author saves agent THEN `agent_plugin_bindings` SHALL persist install + account selection.

---

## Requirement Traceability

| ID | Requirement |
|----|-------------|
| PLG-01 | Host plugin policy (enabled, mode, allowlist, stdio) |
| PLG-02 | Claude manifest parser |
| PLG-03 | Install zip / catalog path |
| PLG-04 | Materialize SkillDefinition + McpServer |
| PLG-05 | Plugin accounts + vault mapping |
| PLG-06 | Agent plugin binding + sync |
| PLG-07 | Runtime MCP gate for needs_auth |
| PLG-08 | Tenancy isolation |
| PLG-09 | Closed catalog UI |
