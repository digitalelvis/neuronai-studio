---
name: canva
description: Create, search, edit, and export Canva designs when the tenant user has authenticated via OAuth.
---

# Canva Connector

Use for design generation, template autofill, library search, and exports (PDF, PNG, etc.).

## Prerequisites

- OAuth per Canva user/tenant — token stored as `var:CANVA_CANVA_ACCESS_TOKEN`.
- Embedded host products may need redirect URI registration (Canva MCP waitlist).
- Pro+ plans required for some features (resize, autofill).

## Guidelines

- Each end user authenticates individually; designs are user-scoped in Canva.
- Confirm export format and dimensions before running export tools.
- Enterprise admins may disable third-party integrations — check access if denied.
