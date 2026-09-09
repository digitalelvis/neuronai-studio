---
name: linear
description: Use Linear MCP tools for issues, projects, cycles, and comments when the tenant has connected Linear credentials.
---

# Linear Connector

Use when the user asks about Linear work tracking, roadmaps, standups, or issue updates.

## Prerequisites

- Plugin installed for the **current tenant**.
- `LINEAR_API_KEY` mapped to a tenant vault variable (`var:LINEAR_LINEAR_API_KEY`).
- P1: Linear personal API key from Linear → Settings → Security & access.
- P3: host OAuth flow per tenant (preferred for end users).

## Guidelines

- Prefer read/search before create/update unless the user explicitly requests a write.
- Reference issue identifiers (TEAM-123) when known.
- Do not guess project or team IDs — use MCP search tools first.
