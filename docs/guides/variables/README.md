# Studio Variable Vault (Global Variables)

Create and bind Studio-managed variables so operators can switch API keys and integration credentials **without editing the host `.env` or redeploying**.

The vault **complements** install-time configuration (`config/neuron.php`, `env:…`, `token_env` / `key_env`). It does not replace it.

## Credential vs Generic

| Type | UI | Storage |
|------|-----|---------|
| **Credential** | Masked (`*****` in lists); edit value blank = keep existing | Encrypted at rest (Laravel Crypt / `APP_KEY`) |
| **Generic** | Visible in lists; may be revealed on edit | Plaintext in DB |

Names must match `^[A-Z][A-Z0-9_]*$` (example: `OPENAI_PROD`).

## Create variables

1. Open **Settings** (rail gear icon) → **Global Variables**
2. **+ Add New** → choose Credential or Generic → Name → Value → Save

## Bind with Variable Input

On supported fields, use the **globe** control to bind a variable. The form stores a stable reference:

```text
var:OPENAI_PROD
```

Never the resolved secret.

### MVP surfaces

- **Agent** → optional API key override (empty = host `neuron.php`)
- **MCP server** → token field (`var:NAME` or classic env **name**)
- **Knowledge base** → `key_env` / `api_key_env` (same dual semantics)

## Runtime resolution

Config strings resolve in this order:

1. Exact `var:NAME` → Studio vault
2. `env:VAR` or whole-string `{{ env.VAR }}` → Laravel `env()`
3. Otherwise literal (for env-name columns: treat as env name)

Missing variables (and empty Credentials) fail with a clear error — no silent empty string for secrets.

### Prompts and state

You can interpolate Generic (or Credential) values in templates:

```text
Base URL: {{ var.BASE_URL }}
```

Prefer **Generic** in prompts. Credentials must never appear in traces or SSE payloads.

## Codegen / export

Exported PHP **keeps** `var:NAME` and resolves at runtime on the host (via `ConfigValueResolver`). Empty agent `api_key` keeps `config('neuron.provider…key')`.

## Security notes

- Encryption depends on **`APP_KEY`**. Rotating `APP_KEY` without re-encrypting Credential rows will break decryption.
- Do not log resolved Credential values.
- List views never show Credential plaintext.
- Back up the variables table like any other secret store.

## Related

- ADR-028 Studio Variable Vault
- Feature specs: `.specs/features/global-variables/`
