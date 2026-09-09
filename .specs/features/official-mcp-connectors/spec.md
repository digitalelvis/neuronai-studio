# Official MCP Connectors — Specification

**Requirement IDs:** `CAT-xx` · **Date:** 2026-09-08  
**Milestone:** M22 · **Line:** `v3.1.x`  
**Parent:** [plugin-system](../plugin-system/spec.md)

## Problem Statement

Hosts with multi-tenant Studio need official example connector packs (Linear, Stripe, HubSpot, Canva, Mercado Pago) in the closed catalog to validate HTTP MCP auth patterns and per-tenant install + vault mapping before building OAuth adapters (P3).

## Goals

- [ ] Ship five HTTP MCP packs in `resources/plugins/marketplace.json`.
- [ ] Each pack declares `token_env` for vault-backed Bearer auth (P1).
- [ ] Install and credentials are isolated per tenant (`tenant_scope`, `var:` — never shared `env:`).
- [ ] Document host OAuth contract (PKCE, CIMD, per-tenant tokens) for P3.

## Out of Scope

| Feature | Reason |
|---------|--------|
| OAuth UI in Studio | Host adapter P3 |
| `mcp-remote` / stdio | `plugins.stdio` off by default |
| Brand icons in package | Avoid third-party assets |

---

## User Stories

### P1: Catalog listings ⭐ MVP

**Acceptance Criteria:**

1. WHEN plugins enabled THEN catalog SHALL list `linear`, `stripe`, `hubspot`, `canva`, `mercadopago` with title and description.
2. WHEN pack is installed THEN system SHALL materialize HTTP `McpServer` with `url` and `token_env` mapped to `var:`.
3. WHEN credential vault empty THEN account status SHALL be `needs_auth`.

### P1: Per-tenant install ⭐ MVP

**Acceptance Criteria:**

1. WHEN tenancy enabled and tenant A installs `linear` THEN install SHALL stamp `tenant_id` of A and SHALL NOT mutate a global install.
2. WHEN tenant B installs the same slug THEN system SHALL create a separate install, MCP row, and vault namespace.
3. WHEN tenant A maps `var:LINEAR_LINEAR_API_KEY` THEN tenant B SHALL NOT resolve A's token.

### P1: Host documentation ⭐ MVP

**Acceptance Criteria:**

1. Docs SHALL describe global catalog vs per-tenant install/auth.
2. Docs SHALL forbid `env:` for connector secrets on SaaS hosts.
3. Docs SHALL map each vendor to token (P1) vs OAuth (P3) requirements.

---

## Requirement Traceability

| ID | Requirement |
|----|-------------|
| CAT-01 | Five official MCP packs in marketplace |
| CAT-02 | HTTP transport + token_env in .mcp.json |
| CAT-03 | Per-tenant plugin install (no global mutation) |
| CAT-04 | Per-tenant MCP materialization |
| CAT-05 | Per-tenant vault via var: mapping |
| CAT-06 | Host auth matrix documentation |
