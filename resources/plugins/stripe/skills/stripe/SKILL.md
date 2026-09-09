---
name: stripe
description: Use Stripe MCP for payments, customers, subscriptions, and documentation search when tenant Stripe credentials are connected.
---

# Stripe Connector

Use for billing, checkout, refunds, customer lookup, and Stripe integration planning.

## Prerequisites

- Tenant vault maps `STRIPE_API_KEY` to a **restricted** API key (`var:STRIPE_STRIPE_API_KEY`).
- Use test keys in non-production tenants.
- P3: Stripe OAuth per tenant (Dashboard → OAuth sessions).

## Guidelines

- Confirm live vs test mode before write operations.
- Prefer `stripe_api_read` / documentation search before destructive writes.
- Connect platforms may need `Stripe-Account` header — configure via host, not in the pack.
