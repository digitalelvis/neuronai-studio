# Global Variables — Tasks

**Design**: [design.md](./design.md) · **Spec**: [spec.md](./spec.md)  
**Status**: Done — Execute GV-T1…T9 complete  
**Line**: `v2.1.x` · **Branch**: `feat/global-variables` → `v2.1.x`

---

## Execution Plan

```
GV-T1 (model/migration) → GV-T2 (ConfigValueResolver)
                              ↓
                    GV-T3 (Settings UI) ──→ GV-T4 (Variable Input) [P after T1]
                              ↓
                    GV-T5 (Agent + ProviderRegistry)
                              ↓
                    GV-T6 (MCP + KB)
                              ↓
                    GV-T7 (interpolator + instructions)
                              ↓
                    GV-T8 (codegen + trace scrub)
                              ↓
                    GV-T9 (docs + spec sync)
```

---

### GV-T1 — Migration, Model, Repository (GV-01, GV-02)

**What**: Create `{prefix}variables` table; `Variable` model with conditional encryption; `VariableRepository::findByName`; register in `StudioTables`.  
**Where**: `database/migrations/…_create_variables_table.php`, `src/Models/Variable.php`, `src/Repositories/VariableRepository.php` (or `src/Support/`), `StudioTables.php`  
**Depends on**: None  
**Reuses**: `StudioTables`, Laravel encrypted cast patterns  
**Done when**:
- [ ] Table has id, unique name, type, value, timestamps
- [ ] Credential encrypts; generic plaintext; type flip works
- [ ] `findByName` returns model or null
- [ ] PHPUnit covers CRUD uniqueness + ciphertext ≠ plaintext
**Tests**: unit (`tests/Variables/VariableModelTest.php`)  
**Gate**: `./vendor/bin/phpunit tests/Variables`  
**Commit**: `feat(variables): add variables table model and repository`

---

### GV-T2 — ConfigValueResolver (GV-07)

**What**: Shared resolver for `var:` + `env:` / `{{ env.* }}`; refactor ToolResolver + McpRegistry env helpers to delegate; `resolveEnvNameOrVar`.  
**Where**: `src/Runtime/ConfigValueResolver.php`, `ToolResolver.php`, `McpRegistry.php`  
**Depends on**: GV-T1  
**Done when**:
- [ ] `var:NAME` hit/miss (clear error)
- [ ] Nested array walk
- [ ] Existing env: behavior preserved
**Tests**: unit (`tests/Variables/ConfigValueResolverTest.php`)  
**Gate**: `./vendor/bin/phpunit tests/Variables`  
**Commit**: `feat(variables): add ConfigValueResolver with var and env resolution`

---

### GV-T3 — Settings UI + nav (GV-03, GV-04)

**What**: Livewire Settings Variables Index + create/edit modal; routes; rail Settings link; auth gate.  
**Where**: `src/Http/Livewire/Settings/Variables/Index.php`, views, `routes/web.php`, `layouts/app.blade.php`  
**Depends on**: GV-T1  
**Done when**:
- [ ] List Name/Type/masked value; empty state; delete confirm
- [ ] Create with type/name/value; name pattern + uniqueness
- [ ] Credential edit blank=keep; Generic may reveal
**Tests**: feature smoke optional; manual browser  
**Gate**: build + quick phpunit  
**Commit**: `feat(variables): add Settings global variables CRUD UI`

---

### GV-T4 — Variable Input component (GV-05)

**What**: Reusable Blade/Alpine Variable Input (literal vs bind, globe search, sensitive mask).  
**Where**: `resources/views/components/variable-input.blade.php` (+ JS/Alpine as needed)  
**Depends on**: GV-T1  
**Done when**:
- [ ] Bound state shows name / stores `var:NAME`
- [ ] Sensitive unbound = masked literal
- [ ] Searchable picker lists vault names
**Tests**: none (UI); covered when wired  
**Gate**: build  
**Commit**: `feat(variables): add Variable Input component`

---

### GV-T5 — Agent api_key + ProviderRegistry (GV-06)

**What**: Migration `api_key` on agent_definitions; Agent Edit Variable Input; ProviderRegistry override; AgentRunner passes key.  
**Where**: migration, `AgentDefinition`, Agents/Edit + blade, `ProviderRegistry`, `AgentRunner`  
**Depends on**: GV-T2, GV-T4  
**Done when**:
- [ ] Empty api_key → host neuron.php
- [ ] `var:NAME` resolves before assertProviderConfigured
- [ ] Saved config stores ref not secret when bound
**Tests**: unit ProviderRegistry override  
**Gate**: `./vendor/bin/phpunit` subset Agents/Provider  
**Commit**: `feat(variables): wire agent api_key override via vault`

---

### GV-T6 — MCP + KB wiring (GV-06)

**What**: Variable Input on MCP token_env and KB key_env/api_key_env; resolveEnvNameOrVar in McpRegistry + VectorStoreFactory (+ Embeddings if applicable).  
**Where**: McpServers/Edit, KnowledgeBases/Edit + blades, registries/factories  
**Depends on**: GV-T2, GV-T4  
**Done when**:
- [ ] `var:NAME` and env-name both work
- [ ] Legacy env-only installs unchanged
**Tests**: unit resolveToken / resolveEnv  
**Gate**: phpunit Variables + related  
**Commit**: `feat(variables): resolve vault refs for MCP and KB keys`

---

### GV-T7 — Prompt/state interpolation (GV-08)

**What**: Extend StateTemplateInterpolator for `{{ var.NAME }}`; interpolate agent instructions before build.  
**Where**: `StateTemplateInterpolator.php`, `AgentRunner` (or instruction assembly)  
**Depends on**: GV-T2  
**Done when**:
- [ ] `{{ var.NAME }}` substitutes; state keys unchanged
- [ ] Missing var errors clearly
**Tests**: unit interpolator  
**Gate**: phpunit  
**Commit**: `feat(variables): interpolate var placeholders in prompts and state`

---

### GV-T8 — Codegen keep refs + no secret traces (GV-10, GV-07)

**What**: Export preserves `var:NAME`; provider emit runtime resolve when override set; ensure traces/SSE do not log Credential plaintext.  
**Where**: `AgentExporter` / `CodegenContext`, telemetry scrub if needed  
**Depends on**: GV-T5  
**Done when**:
- [ ] Export assertion keeps `var:`
- [ ] Empty api_key keeps config() path
- [ ] No Credential in span payloads under normal path
**Tests**: Codegen unit  
**Gate**: phpunit Codegen + Variables  
**Commit**: `feat(variables): keep var refs on export and scrub secrets from traces`

---

### GV-T9 — Docs + ROADMAP/STATE/spec sync (GV-09)

**What**: Operator guide vault vs `.env`; mark GV-01…10 done; update ROADMAP/STATE.  
**Where**: `docs/guides/variables/`, ROADMAP, STATE, spec traceability  
**Depends on**: GV-T3…T8  
**Done when**:
- [ ] Guide covers create/bind/coexistence/`APP_KEY`
- [ ] Spec status Verified for P1 IDs
**Tests**: none  
**Gate**: build  
**Commit**: `docs(variables): add studio variable vault guide`

---

## Traceability

| Req ID | Tasks |
|--------|-------|
| GV-01 | T1 |
| GV-02 | T1 |
| GV-03 | T3 |
| GV-04 | T3 |
| GV-05 | T4 |
| GV-06 | T5, T6 |
| GV-07 | T2, T8 |
| GV-08 | T7 |
| GV-09 | T9 |
| GV-10 | T8 |
| GV-11 | deferred P2 |

---

## Out of Execute

- GV-11 Tool options Variable Input (P2)
- Apply to Fields, multi-tenant UX, canvas `controlled_by`
