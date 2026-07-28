# ADR-028: Studio Variable Vault (Credential + Generic)

**Date:** 2026-07-28  
**Status:** Accepted (product)  
**Feature line:** `v2.1.x`  
**Related:** [PRD](../prd/prd-global-variables.md), [Glossary](glossary-global-variables.md), AD-026 (canvas param variables deferred), AD-020 (env-first observability)

## Context

Hosts configure LLM and integration secrets via `.env` / `config/neuron.php` and env-name columns (`token_env`, `key_env`). That blocks flexible multi-account setups without redeploy. Langflow offers Global Variables (Credential vs Generic, masked UI, field binding). Studio needs the same *product shape*, adapted to Laravel encryption and existing env bridges.

AD-026 deferred canvas tool-param `controlled_by` / variables / tokens at runtime; this ADR covers a **Settings vault + explicit field binding**, not that canvas param model.

## Decision

1. **Ship a Studio Variable Vault** with types **Credential** and **Generic**, managed in a Settings UI.
2. **Credential values are encrypted at rest** in the Studio DB (Laravel encrypted cast / `APP_KEY`).
3. **Authors bind variables via a Variable Input component** on supported string config fields (literal or reference); expand wiring surface incrementally.
4. **Apply to Fields is deferred** (MVP = CRUD + explicit bind + runtime resolve).
5. **Scope is app-wide** for the host install; multi-tenancy is a future extension (no tenant UX in MVP).
6. **Env-first mechanisms remain**; the vault complements them and does not remove install-time `.env` bootstrap for providers.

## Consequences

- New prefixed table + Livewire Settings surface; first “Settings” nav entry in Studio.
- Shared resolver must understand variable references without logging Credential plaintext.
- Schema/APIs should use repository-style access by name so a later `tenant_id` (or equivalent) can be added.
- Canvas tool-param token/variable runtime stays a separate follow-up unless explicitly folded in a later ADR.
- Hosts must treat `APP_KEY` rotation as a vault-breaking event unless a re-encrypt path is added later.

## Alternatives considered

| Option | Why not (now) |
|--------|----------------|
| Env-name only (no DB secrets) | Does not meet “multi-account without redeploy / host secret edit” goal |
| Hybrid encrypted-or-env per row | Useful later; MVP stays simple: vault value XOR continue using existing env bridges on the field |
| Full Langflow Apply to Fields in MVP | Explicitly deferred (D4) |
| Multi-tenant vault in MVP | Premature; app-scoped is enough for package hosts today |
