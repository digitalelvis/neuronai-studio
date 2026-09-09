---
name: mercadopago
description: Search Mercado Pago documentation and validate payment integrations when tenant credentials are connected.
---

# Mercado Pago Connector

Use for integration docs, test users, webhooks, and quality measurement before production.

## Prerequisites

- Bearer access token in tenant vault (`var:MERCADOPAGO_MERCADOPAGO_ACCESS_TOKEN`).
- Use test credentials in sandbox tenants.
- Application management tools require OAuth (host P3).

## Guidelines

- Prefer documentation search before suggesting API changes.
- Distinguish test vs production tokens per tenant.
- Do not expose access tokens in agent responses.
