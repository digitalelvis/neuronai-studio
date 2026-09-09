# Plugins (closed catalog)

NeuronAI Studio supports **host-controlled connector packs** in Claude plugin format (`.claude-plugin/plugin.json`, `skills/`, optional `.mcp.json`).

## Host policy

Configure in `config/neuronai-studio.php` or `.env`:

| Key | Default | Meaning |
|-----|---------|---------|
| `NEURONAI_STUDIO_PLUGINS_ENABLED` | `true` | Show Plugins UI and allow install |
| `NEURONAI_STUDIO_PLUGINS_MODE` | `closed` | `closed` = package catalog + `plugins.catalog` only; `allowlist` adds `plugins.allowlist` |
| `NEURONAI_STUDIO_PLUGINS_STDIO` | `false` | Allow stdio MCP connectors from plugins |

```php
'plugins' => [
    'enabled' => true,
    'mode' => 'closed',
    'stdio' => false,
    'allowlist' => [],
    'catalog' => [],
    'catalog_paths' => [],
],
```

## Multi-tenant hosts

When `neuronai-studio.tenancy.enabled` is true:

| Layer | Scope |
|-------|--------|
| **Catalog** | Global — same `marketplace.json` for every tenant |
| **Install** | Per tenant — `plugin_installs` unique on `(tenant_scope, slug)` |
| **Credentials** | Per tenant — `plugin_accounts.credential_map` → `var:NAME` in the tenant vault |
| **Materialized MCP** | Per tenant — `mcp_servers` unique on `(tenant_scope, slug)` |

Flow:

1. Host resolves tenant on HTTP / integrate / jobs (see [optional multi-tenancy](../../.specs/features/optional-multi-tenancy/spec.md)).
2. Tenant author opens **Connectors** → installs packs from the catalog (e.g. Linear, Stripe).
3. **Manage credentials** → map keys to `var:…` variables created for that install.
4. Bind plugin on an **Agent** in the same tenant.

**Never map connector secrets to `env:` on SaaS hosts** — process `.env` is shared and breaks isolation. Use `var:NAME` only.

Each tenant may install different packs and use different API keys or OAuth-derived access tokens.

## Install flow

1. Open **Connectors** in Studio.
2. **Add** a listing from the closed catalog (built-in: `demo-assistant`, plus official MCP packs).
3. Open **Manage** → map env keys to `var:NAME` credentials.
4. On an **Agent**, select installed plugins; skills + MCP sync automatically.

## Official MCP connector packs

Shipped in `resources/plugins/marketplace.json`. All use **HTTP** transport (no `npx mcp-remote`).

| Pack | MCP URL | P1 (vault) | Vendor auth | Host P3 notes |
|------|---------|------------|-------------|---------------|
| Linear | `https://mcp.linear.app/mcp` | API key Bearer | OAuth 2.1 or API key | [Linear MCP](https://linear.app/docs/mcp) — OAuth per tenant |
| Stripe | `https://mcp.stripe.com` | Restricted API key | OAuth or API key | [Stripe MCP](https://docs.stripe.com/mcp) |
| HubSpot | `https://mcp.hubspot.com` | Access token | OAuth **PKCE** + MCP Auth App | One OAuth app per host; tokens per tenant portal |
| Canva | `https://mcp.canva.com/mcp` | MCP access token | MCP OAuth (PKCE) | **Not** Connect API (`OC-…`). Register MCP client → authorize `https://mcp.canva.com/authorize`, token `https://mcp.canva.com/token`. Redirect: `{APP_URL}/{route_prefix}/plugins/oauth/callback` |
| Mercado Pago | `https://mcp.mercadopago.com/mcp` | Access token | OAuth or Bearer | App-management tools require OAuth |

P1: authors paste API keys or access tokens into tenant **Variables** referenced by the plugin account.

OAuth (Studio **Autenticar** button): configure `plugins.oauth.providers` in `config/neuronai-studio.php`. All providers share one redirect URI:

```
{APP_URL}/{route_prefix}/plugins/oauth/callback
```

**Canva (important):** the plugin talks to `https://mcp.canva.com/mcp`. Tokens from the [Canva Connect API](https://www.canva.dev/docs/connect/authentication/) (`OC-…` Developer Portal apps) **do not work** on the MCP server. Register an MCP OAuth client:

```bash
curl --location 'https://mcp.canva.com/register' \
  --header 'Content-Type: application/json' \
  --data '{
    "client_name": "My Studio",
    "redirect_uris": ["http://127.0.0.1:8000/neuronai-studio/plugins/oauth/callback"],
    "grant_types": ["authorization_code"]
  }'
```

Put the returned `client_id` / `client_secret` in `NEURONAI_STUDIO_OAUTH_CANVA_*`, then **Autenticar** again in Studio.

Store `access_token` / `refresh_token` in the **current tenant's** vault only; never share OAuth sessions across tenants. HubSpot requires PKCE.

## Package catalog

Official listings ship in `resources/plugins/marketplace.json`. Hosts can add paths via `plugins.catalog` or `plugins.catalog_paths`.

## Security

- No third-party PHP in plugins.
- Stdio MCP requires host allowlist + `plugins.stdio`.
- Open marketplace mode is not supported in this release.
- Runtime skips plugin MCP tools when account is `needs_auth`.
