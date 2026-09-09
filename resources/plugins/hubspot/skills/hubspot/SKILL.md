---
name: hubspot
description: Query and update HubSpot CRM, campaigns, and content when the tenant has a HubSpot access token from OAuth.
---

# HubSpot Connector

Use for CRM search, deals, contacts, marketing emails, and campaign analytics.

## Prerequisites

- **OAuth only** — no static API key for MCP.
- Host must run OAuth with **PKCE** using an MCP Auth App (HubSpot → Development → MCP Auth Apps).
- Store the resulting access token in the tenant vault as `var:HUBSPOT_HUBSPOT_ACCESS_TOKEN`.
- P3: host refresh-token rotation per tenant portal.

## Guidelines

- Respect HubSpot user permissions exposed by `get_user_details`.
- Sensitive Data accounts block some activity/conversation objects via MCP.
- Confirm before campaign or email draft writes.
