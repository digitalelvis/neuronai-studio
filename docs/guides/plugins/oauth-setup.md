# Plugin OAuth setup

Studio can start OAuth for official MCP connector packs via **Authenticate** in the connector detail (or credentials) modal. Tokens are stored per tenant in the vault (`var:…`).

## Shared redirect URI

All providers use the same callback:

```
{APP_URL}/{route_prefix}/plugins/oauth/callback
```

Defaults (`APP_URL=http://127.0.0.1:8000`, `route_prefix=neuronai-studio`):

```
http://127.0.0.1:8000/neuronai-studio/plugins/oauth/callback
```

Register this exact URI in each vendor OAuth app.

## Environment variables

```env
NEURONAI_STUDIO_OAUTH_LINEAR_CLIENT_ID=
NEURONAI_STUDIO_OAUTH_LINEAR_CLIENT_SECRET=
NEURONAI_STUDIO_OAUTH_STRIPE_CLIENT_ID=
NEURONAI_STUDIO_OAUTH_STRIPE_CLIENT_SECRET=
NEURONAI_STUDIO_OAUTH_HUBSPOT_CLIENT_ID=
NEURONAI_STUDIO_OAUTH_HUBSPOT_CLIENT_SECRET=
NEURONAI_STUDIO_OAUTH_CANVA_CLIENT_ID=
NEURONAI_STUDIO_OAUTH_CANVA_CLIENT_SECRET=
NEURONAI_STUDIO_OAUTH_MERCADOPAGO_CLIENT_ID=
NEURONAI_STUDIO_OAUTH_MERCADOPAGO_CLIENT_SECRET=
```

Provider endpoints and scopes live in `config/neuronai-studio.php` → `plugins.oauth.providers`.

The **Authenticate** button appears only when `client_id` is set for that slug.

## Refresh tokens

When the provider returns `expires_in` and a refresh token:

1. Studio stores `access_token` and `refresh_token` in the tenant vault.
2. `plugin_accounts.token_expires_at` is set from `expires_in`.
3. Before resolving MCP tools, Studio refreshes the access token if expiry is within 60 seconds (or already past).
4. If refresh fails, the account is disconnected (`needs_auth`) and MCP tools are skipped until the user authenticates again.

Configure `refresh_token_env` per provider (defaults ship for Linear, Stripe, HubSpot, Canva, Mercado Pago).

## Provider notes

| Pack | Authorize / token | Notes |
|------|-------------------|--------|
| Linear | `linear.app/oauth/authorize` · `api.linear.app/oauth/token` | [Linear OAuth](https://linear.app/docs/oauth-authentication-2lo) |
| Stripe | Connect OAuth | Restricted API key still works without OAuth |
| HubSpot | `app.hubspot.com/oauth/authorize` · PKCE | MCP Auth App; one host app, tokens per tenant |
| Canva | **`mcp.canva.com/authorize` / `token`** | **Not** Developer Portal `OC-…` Connect API apps. Register MCP client (below). |
| Mercado Pago | `auth.mercadopago.com` · `api.mercadopago.com/oauth/token` | App credentials from Mercado Pago Developers |

### Canva MCP client registration

```bash
curl --location 'https://mcp.canva.com/register' \
  --header 'Content-Type: application/json' \
  --data '{
    "client_name": "My Studio",
    "redirect_uris": ["http://127.0.0.1:8000/neuronai-studio/plugins/oauth/callback"],
    "grant_types": ["authorization_code"]
  }'
```

Use the returned `client_id` / `client_secret` in `NEURONAI_STUDIO_OAUTH_CANVA_*`. Connect API credentials (`OC-…`) are rejected by Studio because they do not work with `https://mcp.canva.com/mcp`.

## Security

- Never map connector secrets to process `env:` on multi-tenant hosts — use `var:NAME` only.
- OAuth sessions and vault values are tenant-scoped.
- Disconnect clears vault values and `token_expires_at` without uninstalling the pack.
