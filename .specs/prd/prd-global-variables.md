# PRD: Global Variables (Studio Vault)

## Introduction / Overview

NeuronAI Studio today is **env-first**: LLM providers, MCP tokens, vector-store keys, and similar secrets live in the host `.env` / `config/neuron.php`, with string bridges such as `token_env`, `key_env`, `env:…`, and `{{ env.VAR }}`. That works for a single deploy, but forces redeploys (or host-level secret edits) whenever an author needs another API key, another vendor account, or a non-secret default shared across agents and workflows.

This feature adds a **Studio-managed variable vault** inspired by Langflow Global Variables: operators create named variables of type **Credential** (sensitive, masked, encrypted at rest) or **Generic** (plain values), then bind them into supported configuration fields via a dedicated **Variable Input** component (globe picker + optional mask), so models and integrations can switch accounts/auth without redeploying the host app.

**MVP scope (locked):** vault CRUD + secure storage + explicit field binding via the Variable Input component. **Apply to Fields** (auto-default by field type) is deferred to a later slice.

## Goals

- Allow creating, listing, updating, and deleting Studio variables without touching `.env`
- Support **Credential** (masked UI, encrypted DB) and **Generic** (visible, non-encrypted or lightly protected) types
- Let authors bind a variable into any **supported** string config field via a reusable Variable Input component (Langflow-style globe)
- Resolve variable values at runtime for interpreted runs (agents, workflows, tools, MCP, RAG) where the component is wired
- Keep existing `.env` / `env:` resolution working side-by-side (complement, not replace)
- Stay **app-scoped** (one vault per host install); design storage so multi-tenancy can be added later without a rewrite
- Never leak Credential plaintext in list/API responses, logs, or traces

## User Stories

### US-001: Persist Studio variables
**Description:** As a developer, I need a durable store for named variables so values survive restarts and are shared across Studio entities.

**Acceptance Criteria:**
- [ ] Migration creates prefixed table (e.g. `{prefix}variables`) with at least: `id`, `name` (unique), `type` (`credential` | `generic`), `value`, `timestamps`
- [ ] Model uses `StudioTables` / table prefix convention
- [ ] No tenant column required in MVP; document extension point for future multi-tenancy
- [ ] PHPUnit covers create/read/update/delete uniqueness
- [ ] Typecheck/lint passes

### US-002: Encrypt Credential values at rest
**Description:** As an operator, I want Credential values encrypted in the database so a DB dump does not expose API keys in plaintext.

**Acceptance Criteria:**
- [ ] `type=credential` uses Laravel encrypted cast (or equivalent) for `value`
- [ ] `type=generic` stores plaintext value (no encryption requirement)
- [ ] Updating type from generic → credential encrypts on save; credential → generic decrypts then stores plaintext
- [ ] Unit tests assert ciphertext ≠ plaintext for credentials in DB
- [ ] Typecheck/lint passes

### US-003: Settings UI — list and manage variables
**Description:** As a Studio operator, I want a Settings-style page to manage global variables so I can add keys for different accounts without leaving the Studio.

**Acceptance Criteria:**
- [ ] Route under Studio auth gate (e.g. `/neuronai-studio/settings/variables` or equivalent)
- [ ] Table shows Name, Type badge, Value (masked `*****` for Credential; visible for Generic), actions
- [ ] “+ Add New” opens create modal/form
- [ ] Bulk or row delete with confirmation
- [ ] Empty state when no variables exist
- [ ] Typecheck/lint passes
- [ ] Verify in browser using dev-browser skill

### US-004: Create / edit variable modal
**Description:** As a Studio operator, I want to create a variable with type, name, and value so it becomes available across flows.

**Acceptance Criteria:**
- [ ] Required fields: Type (Credential | Generic toggle), Name, Value
- [ ] Credential Value input supports show/hide (eye toggle); default hidden
- [ ] Name uniqueness validated with clear error
- [ ] Name restricted to a stable identifier pattern (e.g. `^[A-Z][A-Z0-9_]*$` or documented equivalent)
- [ ] Cancel / Save / close behave as expected; Save persists and returns to list
- [ ] Edit can update value; Credential edit never pre-fills plaintext unless user explicitly reveals (prefer empty + “leave blank to keep” or always re-enter — document choice in design)
- [ ] Typecheck/lint passes
- [ ] Verify in browser using dev-browser skill

### US-005: Variable Input component (picker)
**Description:** As an author, I want a dedicated input on supported fields (globe + optional mask) so I can type a literal or bind a Studio variable without memorizing syntax.

**Acceptance Criteria:**
- [ ] Reusable Livewire/Blade (and/or Alpine/JS) component usable on agent, workflow node, tool, MCP, and KB forms
- [ ] Modes: literal value vs variable reference (globe opens searchable variable list)
- [ ] Credential-type fields default to masked literal input when not bound to a variable
- [ ] Bound state shows variable name (not resolved secret) in the UI
- [ ] Component documents which props mark a field as “supported”
- [ ] Typecheck/lint passes
- [ ] Verify in browser using dev-browser skill

### US-006: Wire Variable Input on first supported surfaces
**Description:** As an author, I want variable binding on the highest-value auth fields so I can switch provider/integration accounts from the vault.

**Acceptance Criteria:**
- [ ] MVP wires the component on an agreed initial set (see Open Questions / design): at minimum LLM-related API key / provider credential surfaces **or** MCP `token` + KB `*_env` replacements — pick one coherent slice and list fields in tasks
- [ ] Saved config stores a stable reference (not resolved secret), e.g. structured marker or `var:NAME` string
- [ ] Existing literal / `env:` values remain valid
- [ ] Typecheck/lint passes
- [ ] Verify in browser using dev-browser skill

### US-007: Runtime resolution
**Description:** As the runtime, I need to resolve variable references to concrete strings before calling providers/tools so interpreted runs use the vault value.

**Acceptance Criteria:**
- [ ] Shared resolver (extend or sibling of `ToolResolver::resolveConfigValue`) resolves Studio variable refs
- [ ] Missing / disabled variable fails with a clear error (no silent empty string for Credentials)
- [ ] Resolution used on interpreted agent/workflow paths for wired fields
- [ ] Credential values never written to trace/span payloads or SSE events
- [ ] PHPUnit covers resolve hit, miss, and nested array walk
- [ ] Typecheck/lint passes

### US-008: Docs for operators
**Description:** As a host integrator, I want docs explaining vault vs `.env` so I know when to use each.

**Acceptance Criteria:**
- [ ] Guide covers: create Credential/Generic, bind via Variable Input, coexistence with `env:` / `neuron.php`
- [ ] Security notes: encryption depends on `APP_KEY`; rotate carefully; do not log secrets
- [ ] Typecheck/lint / doc build as applicable

## Functional Requirements

- FR-1: The system must allow CRUD of variables with unique `name` and `type` ∈ {`credential`, `generic`}.
- FR-2: Credential `value` must be encrypted at rest; Generic `value` may be stored as plaintext.
- FR-3: List/UI must mask Credential values; never return decrypted Credential values in index JSON/HTML except on intentional reveal in edit (if allowed).
- FR-4: The system must provide a Settings page to manage variables (list + create/edit/delete).
- FR-5: The system must provide a reusable Variable Input component (literal vs variable bind, globe picker, mask for sensitive fields).
- FR-6: Supported fields must persist a stable variable reference, not the resolved secret.
- FR-7: At runtime, the system must resolve variable references before provider/tool/MCP/RAG calls for wired fields.
- FR-8: Existing `env:` / `{{ env.* }}` / `*_env` mechanisms must continue to work; variables complement them.
- FR-9: Authorization must use the existing Studio gate/middleware; no public unauthenticated access to variable values.
- FR-10: Storage must be app-scoped in MVP; schema/docs must note a future multi-tenancy extension point (no tenant UX in MVP).
- FR-11: Apply to Fields (auto-map variable → field type catalog) is **out of MVP** (see Non-Goals).

## Non-Goals (Out of Scope)

- **Apply to Fields** / auto-default by field type catalog (Langflow “Selected fields will auto-apply…”) — deferred
- Replacing host `.env` / `config/neuron.php` as the install-time provider bootstrap
- Multi-tenant / per-team variable isolation UX (future)
- Secret managers as backends (AWS Secrets Manager, Vault, etc.)
- Variable versioning, audit log of secret reads, or rotation workflows
- Sharing variables across multiple Laravel apps / packages
- Canvas tool-param `controlled_by` / token binding runtime (still deferred per AD-026; this feature may unblock later, but is not that work)
- Codegen snapshot policy beyond “document whether export embeds refs or resolves” (finalize in design; no requirement to invent a new export secret store)

## Design Considerations

- **UX reference:** Langflow Global Variables settings table + Create Variable modal (Credential | Generic) + field globe picker (see attached screenshots in chat assets).
- **Component:** Prefer one Studio Variable Input used everywhere support is added; avoid one-off pickers per form.
- **Navigation:** New Settings area (or first Settings page) — Studio has no Settings nav today; introduce minimal Settings shell with “Global Variables” as first item (OBS-06 status page remains separate/deferred).
- **Visual:** Type badges for Credential vs Generic; masked value column; confirm delete.
- **Reuse:** Livewire Index/Edit patterns from Tools / MCP Servers / Knowledge Bases.

## Technical Considerations

- Table prefix via `StudioTables` / `neuronai_studio_` (or configured prefix).
- Encryption: Laravel `encrypted` cast tied to `APP_KEY` — document backup/`APP_KEY` rotation impact.
- Resolution order (proposal for design to confirm): literal → `var:` / structured ref → existing `env:` / `{{ env.* }}`.
- Coexistence: MCP `token_env` and KB `key_env` may gradually accept variable refs **or** gain parallel “use variable” mode via Variable Input — prefer not to break hosts that only set env names.
- Future multi-tenancy: keep a single flat table in MVP; avoid hardcoding “global singleton” assumptions in resolver APIs (e.g. `VariableRepository::findByName(string $name): ?Variable` as the seam).
- Performance: variables are few; cache-by-name optional, not required for MVP.
- Line: target feature work on `v2.1.x` (current feature line per STATE).

## Success Metrics

- Operator can add a Credential and bind it to a supported field in under 2 minutes without editing `.env`
- Switching between two API keys for the same provider requires only vault + field rebind (no redeploy)
- Zero Credential plaintext in list views, traces, or test logs under normal operation
- Existing env-based installs keep working without migration mandatory

## Open Questions (resolved 2026-07-28)

| ID | Resolution |
|----|------------|
| OQ-1 | Wire format **`var:NAME`** (parallel to `env:VAR`). Prompt/state also use `{{ var.NAME }}`. |
| OQ-2 | MVP wiring: **Agent provider API key override** + **MCP token** + **KB `key_env`/`api_key_env`**. Tool options = P2. |
| OQ-3 | Credential edit: value **blank = keep**. Generic: **may reveal** plaintext. |
| OQ-4 | **`{{ var.NAME }}` interpolable** in prompts/state in MVP. |
| OQ-5 | Codegen/export **keeps** `var:NAME`; host resolves at runtime. |
| OQ-6 | **No** `tenant_id` in MVP; `VariableRepository::findByName` seam only. |

See [.specs/features/global-variables/](../features/global-variables/).

## Decisions Locked (from grilling)

| ID | Decision |
|----|----------|
| D1 | Primary goal: Studio vault complementary to `.env` for multi-account/auth without redeploy |
| D2 | Credential values encrypted in Studio DB |
| D3 | Consumption via dedicated Variable Input on supported fields (any string config surface we wire) |
| D4 | Apply to Fields deferred |
| D5 | App-scoped vault now; multi-tenancy later |

See also: [glossary](../domain/glossary-global-variables.md), [ADR-028](../domain/adr-028-studio-variable-vault.md).
