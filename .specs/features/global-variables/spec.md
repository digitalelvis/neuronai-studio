# Global Variables (Studio Variable Vault) Specification

**Context:** [context.md](./context.md) · **Design:** [design.md](./design.md) · **Tasks:** [tasks.md](./tasks.md)  
**PRD:** [prd-global-variables.md](../../prd/prd-global-variables.md) · **ADR:** [adr-028](../../domain/adr-028-studio-variable-vault.md)  
**Line:** `v2.1.x`

## Problem Statement

NeuronAI Studio is env-first: LLM keys, MCP tokens, and vector-store secrets live in host `.env` / `config/neuron.php`. Authors cannot switch accounts or add keys without redeploying or editing host secrets. The Studio needs a Langflow-style variable vault with Credential/Generic types, field binding, and runtime resolution — complementary to existing env bridges.

## Goals

- [ ] Operators CRUD Studio variables without touching `.env`
- [ ] Credential values encrypted at rest; masked in list UI
- [ ] Authors bind `var:NAME` via Variable Input on Agent API key, MCP token, and KB key fields
- [ ] Runtime resolves `var:` (and existing `env:`) before provider/tool/MCP/RAG calls
- [ ] `{{ var.NAME }}` works in prompts/state templates
- [ ] Export keeps `var:NAME`; env-based installs keep working

## Out of Scope

| Feature | Reason |
|---------|--------|
| Apply to Fields | Deferred (D4) |
| Multi-tenant vault UX | Premature; app-scoped MVP |
| Secret-manager backends | Out of MVP |
| Canvas tool-param `controlled_by` | AD-026 separate |
| Replace install-time `.env` bootstrap | Vault complements |
| Tool constructor/options Variable Input | P2 |

---

## User Stories

### P1: Persist Studio variables ⭐ MVP

**User Story**: As a developer, I need a durable store for named variables so values survive restarts and are shared across Studio entities.

**Why P1**: Foundation for vault.

**Acceptance Criteria**:

1. WHEN migrations run THEN system SHALL create `{prefix}variables` with `id`, unique `name`, `type` (`credential`|`generic`), `value`, timestamps.
2. WHEN the model is used THEN it SHALL use `StudioTables` / configured table prefix.
3. WHEN two variables share a name THEN system SHALL reject the second (uniqueness).
4. WHEN schema is reviewed THEN there SHALL be no `tenant_id` (extension via repository seam only).

**Independent Test**: PHPUnit CRUD + unique name constraint.

---

### P1: Encrypt Credential values ⭐ MVP

**User Story**: As an operator, I want Credential values encrypted in the database so a DB dump does not expose API keys.

**Acceptance Criteria**:

1. WHEN `type=credential` is saved THEN `value` SHALL be stored encrypted (Laravel encrypted cast / equivalent tied to `APP_KEY`).
2. WHEN `type=generic` is saved THEN `value` MAY be plaintext.
3. WHEN type flips generic→credential THEN value SHALL encrypt on save; credential→generic SHALL decrypt then store plaintext.
4. WHEN DB row for credential is read raw THEN ciphertext SHALL NOT equal plaintext.

**Independent Test**: Unit test ciphertext ≠ plaintext for credentials.

---

### P1: Settings UI — list and manage ⭐ MVP

**User Story**: As a Studio operator, I want a Settings page to manage global variables without leaving the Studio.

**Acceptance Criteria**:

1. WHEN authenticated under Studio gate THEN `/neuronai-studio/settings/variables` (or equivalent) SHALL be reachable.
2. WHEN variables exist THEN table SHALL show Name, Type badge, Value (masked `*****` for Credential; visible for Generic), and actions.
3. WHEN no variables exist THEN empty state SHALL show.
4. WHEN user deletes THEN confirmation SHALL be required.
5. WHEN Settings nav is opened THEN rail SHALL include Settings → Global Variables (first Settings entry).

**Independent Test**: Browser verify list / empty / delete confirm.

---

### P1: Create / edit variable modal ⭐ MVP

**User Story**: As a Studio operator, I want to create/edit a variable with type, name, and value.

**Acceptance Criteria**:

1. WHEN creating THEN Type (Credential|Generic), Name, and Value SHALL be required (Value required on create).
2. WHEN type is Credential THEN value input SHALL default hidden with show/hide toggle.
3. WHEN name is invalid or duplicate THEN system SHALL show a clear error (`^[A-Z][A-Z0-9_]*$`).
4. WHEN editing Credential AND value is left blank THEN system SHALL keep the existing value.
5. WHEN editing Generic THEN system MAY prefill/reveal plaintext value.

**Independent Test**: Browser create credential, edit blank keep, generic reveal.

---

### P1: Variable Input component ⭐ MVP

**User Story**: As an author, I want a globe picker on supported fields so I can type a literal or bind a Studio variable.

**Acceptance Criteria**:

1. WHEN component is used THEN modes SHALL support literal value vs variable reference (`var:NAME`).
2. WHEN bound THEN UI SHALL show variable name, not resolved secret.
3. WHEN field is marked sensitive AND unbound THEN literal input SHALL be masked by default.
4. WHEN globe opens THEN list SHALL be searchable.

**Independent Test**: Browser bind/unbind on a wired form field.

---

### P1: Wire Agent / MCP / KB ⭐ MVP

**User Story**: As an author, I want variable binding on the highest-value auth fields.

**Acceptance Criteria**:

1. WHEN Agent Edit saves an API key override THEN system SHALL persist `var:NAME` or empty (host config fallback), never resolved secret in DB when bound.
2. WHEN MCP `token_env` is `var:NAME` OR an env name THEN runtime SHALL resolve accordingly.
3. WHEN KB `key_env` / `api_key_env` is `var:NAME` OR an env name THEN RAG factories SHALL resolve accordingly.
4. WHEN existing literal / env-name / `env:` values exist THEN they SHALL remain valid.

**Independent Test**: Unit/integration resolve paths for agent key, MCP, KB.

---

### P1: Runtime resolution ⭐ MVP

**User Story**: As the runtime, I need to resolve variable references before calling providers/tools.

**Acceptance Criteria**:

1. WHEN a config string is exactly `var:NAME` THEN shared resolver SHALL return vault value.
2. WHEN variable is missing THEN system SHALL fail with a clear error (no silent empty for Credentials).
3. WHEN value is `env:VAR` or `{{ env.VAR }}` THEN behavior SHALL match today.
4. WHEN Credential resolves THEN plaintext SHALL NOT be written to trace/span payloads or SSE events.

**Independent Test**: PHPUnit hit, miss, nested array walk.

---

### P1: Prompt / state `{{ var.NAME }}` ⭐ MVP

**User Story**: As an author, I want Generic (and Credential) variables interpolable in prompts and state templates.

**Acceptance Criteria**:

1. WHEN template contains `{{ var.NAME }}` THEN `StateTemplateInterpolator` SHALL substitute the vault value.
2. WHEN agent instructions contain `{{ var.NAME }}` THEN system SHALL interpolate before agent build.
3. WHEN `{{ input }}` / other state keys appear THEN behavior SHALL remain unchanged.

**Independent Test**: Unit interpolator + instructions path.

---

### P1: Operator docs ⭐ MVP

**User Story**: As a host integrator, I want docs explaining vault vs `.env`.

**Acceptance Criteria**:

1. WHEN docs are read THEN guide SHALL cover create Credential/Generic, bind via Variable Input, coexistence with `env:` / `neuron.php`.
2. WHEN security notes are read THEN they SHALL mention `APP_KEY` encryption/rotation and no logging of secrets.

**Independent Test**: Doc file exists and covers checklist.

---

### P1: Codegen keeps `var:NAME` ⭐ MVP

**User Story**: As an exporter, I want exported PHP to keep vault refs for runtime resolve on the host.

**Acceptance Criteria**:

1. WHEN export encounters `var:NAME` in wired fields THEN generated code SHALL preserve the reference (or emit a runtime resolve helper), not embed vault plaintext.
2. WHEN agent `api_key` is empty THEN export SHALL keep `config('neuron.provider…key')` behavior.

**Independent Test**: Codegen unit assertion on stub/output.

---

### P2: Tool config Variable Input

**User Story**: As an author, I want Variable Input on tool constructor/options fields.

**Acceptance Criteria**:

1. WHEN tool options use Variable Input THEN saved values MAY be `var:NAME` and resolve via `ConfigValueResolver`.

**Independent Test**: Form + resolve smoke.

---

## Edge Cases

- WHEN variable name is renamed THEN existing `var:OLD` refs SHALL break until rebound (no cascade rename in MVP).
- WHEN Credential value is empty string after resolve THEN system SHALL error (treat as misconfigured).
- WHEN `APP_KEY` changes THEN existing Credential ciphertext SHALL fail to decrypt (documented).
- WHEN Generic is used in prompts THEN value MAY appear in LLM context (author responsibility).
- WHEN list API/HTML renders Credential THEN value SHALL be `*****` only.

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
|----------------|-------|-------|--------|
| GV-01 | Persist variables | Verified | Verified |
| GV-02 | Encrypt credentials | Verified | Verified |
| GV-03 | Settings list UI | Verified | Verified |
| GV-04 | Create/edit modal | Verified | Verified |
| GV-05 | Variable Input component | Verified | Verified |
| GV-06 | Wire Agent/MCP/KB | Verified | Verified |
| GV-07 | Runtime resolver | Verified | Verified |
| GV-08 | Prompt/state interpolation | Verified | Verified |
| GV-09 | Operator docs | Verified | Verified |
| GV-10 | Codegen keep refs | Verified | Verified |
| GV-11 | Tool Variable Input | P2 | Pending |

**Coverage:** 11 total, 0 mapped to tasks until tasks.md, 11 unmapped until Tasks phase.

---

## Success Criteria

- [ ] Operator adds a Credential and binds it to a supported field in under 2 minutes without editing `.env`
- [ ] Switching two API keys for the same provider requires only vault + field rebind (no redeploy)
- [ ] Zero Credential plaintext in list views, traces, or test logs under normal operation
- [ ] Existing env-based installs keep working without mandatory migration of secrets into the vault
