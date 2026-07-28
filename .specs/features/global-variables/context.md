# Global Variables (Studio Variable Vault) — Context

**Gathered:** 2026-07-28  
**Feature line:** `v2.1.x` (AD-027)  
**Status:** Execute done (GV-T1…T9) on `feat/global-variables`  
**Spec:** [spec.md](./spec.md) · [design](./design.md) · [tasks](./tasks.md)  
**Related:** [PRD](../../prd/prd-global-variables.md) · [ADR-028](../../domain/adr-028-studio-variable-vault.md) · [glossary](../../domain/glossary-global-variables.md)

---

## Feature Boundary

Ship a **Studio-managed variable vault** (Credential + Generic) with Settings CRUD, a reusable **Variable Input** (literal vs `var:NAME` bind), runtime resolution for wired auth/config fields, and `{{ var.NAME }}` interpolation in prompts/state. Complements — does not replace — host `.env` / `config/neuron.php` and existing `env:` / `*_env` bridges.

**MVP does not include:** Apply to Fields, multi-tenant UX, secret-manager backends, canvas tool-param `controlled_by` runtime, Tool constructor/options Variable Input (P2).

---

## Implementation Decisions (locked)

### Wire format (OQ-1)

- Persist **`var:NAME`** (exact string prefix), parallel to `env:VAR`.
- UI Variable Input writes `var:NAME` when bound; shows variable name, never resolved Credential plaintext.

### First wiring slice (OQ-2)

MVP wires Variable Input on:

1. **Agent provider API key override** (`api_key` on agent definition; empty = host `neuron.php`)
2. **MCP** `token_env` (accepts `var:NAME` or env name)
3. **KB** `key_env` / `api_key_env` in vector store config (same dual semantics)

Tool constructor/options Variable Input = **P2**.

### Credential / Generic edit UX (OQ-3)

- **Credential:** edit value field starts blank; blank on save = keep existing ciphertext.
- **Generic:** may reveal/show plaintext in edit.

### Prompt / state interpolation (OQ-4)

- `{{ var.NAME }}` resolved in `StateTemplateInterpolator` and agent **instructions** path.
- Prefer Generic in prompts; Credential may resolve but must never appear in traces/SSE.

### Codegen / export (OQ-5)

- Export **keeps** `var:NAME`; host resolves at runtime (no embed of vault secrets).
- Empty agent `api_key` keeps today’s `config('neuron.provider…key')` emit.

### Multi-tenancy (OQ-6)

- **No** `tenant_id` column in MVP.
- Access via `VariableRepository::findByName(string $name)` as the extension seam.

### Also locked (PRD / ADR-028)

| ID | Decision |
|----|----------|
| D1 | Vault complementary to `.env` for multi-account without redeploy |
| D2 | Credential values encrypted at rest (`APP_KEY`) |
| D3 | Consumption via Variable Input on supported fields |
| D4 | Apply to Fields deferred |
| D5 | App-scoped vault; multi-tenancy later |

### Resolution order (config strings)

1. Non-string → passthrough  
2. Exact `var:NAME` → vault (miss / empty Credential → clear error)  
3. `env:VAR` / whole-string `{{ env.VAR }}`  
4. Else literal; for env-name columns (`token_env`, `key_env`), if not `var:`/`env:` → `env($name)` as today

---

## Deferred Ideas

- Apply to Fields / field-type catalog
- Canvas tool-param `controlled_by` / token binding runtime (AD-026)
- Multi-tenant / per-team vault UX
- AWS Secrets Manager / HashiCorp Vault backends
- Variable versioning, audit of secret reads, rotation workflows
- Tool options Variable Input (P2)
- Agent LLM key in canvas agent-node override beyond definition-level `api_key` (if needed later)

---

## Specific References

- Langflow Global Variables settings table + Create Variable modal + field globe picker
- Existing Livewire Index/Edit: Tools, MCP Servers, Knowledge Bases
- Shared env resolution today: `ToolResolver::resolveConfigValue`, `McpRegistry::resolveEnvValue`

---

## Agent's Discretion

- Exact Livewire modal vs separate Edit page for variables (prefer Index + modal for CRUD density)
- Alpine vs pure Livewire for globe picker search
- Column name `api_key` on `agent_definitions` (nullable string)
- Whether Generic uses no cast vs `encrypted` always-off path (prefer conditional cast / custom cast)
