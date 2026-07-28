# Studio i18n Specification

**Context:** [context.md](./context.md) · **Design:** [design.md](./design.md) · **Tasks:** [tasks.md](./tasks.md)  
**Line:** `v2.1.x` · **Decision:** AD-029

## Problem Statement

NeuronAI Studio ships English-only UI chrome (Blade, Livewire flashes, registry labels, React bundles, Artisan). Brazilian hosts need Portuguese without forking views. The package has no `lang/` catalogs or `loadTranslationsFrom`.

## Goals

- [ ] Host `APP_LOCALE=pt_BR` (or package locale override) renders Studio chrome in Portuguese
- [ ] Default / missing keys fall back to English catalogs
- [ ] Hosts can publish and override translations via `neuronai-studio-lang`
- [ ] Phased coverage: P1 PHP/Blade → P2 JS → P3 CLI

## Out of Scope

| Feature | Reason |
|---------|--------|
| In-Studio language switcher | Host/app locale owns it |
| Locales beyond en/pt_BR | Same catalogs later |
| Agent prompts / template bodies | Author content, not chrome |
| Framework-wide Laravel validation | Host owns `lang/`; Studio only custom messages |
| RTL / date polish | Not MVP |

---

## User Stories

### P1: Package translation loading ⭐ MVP

**User Story**: As a host integrator, I want Studio translations loaded under a package namespace so keys resolve without publishing views.

**Acceptance Criteria**:

1. WHEN the service provider boots THEN system SHALL `loadTranslationsFrom` under namespace `neuronai-studio`.
2. WHEN a view calls `__('neuronai-studio::ui.nav.agents')` THEN system SHALL return the string for the active locale.

**Independent Test**: PHPUnit asserts EN and pt_BR values for a sample key.

---

### P1: Locale resolution ⭐ MVP

**User Story**: As a host, I want Studio to follow my app locale, with an optional package override.

**Acceptance Criteria**:

1. WHEN `neuronai-studio.locale` is a non-empty string THEN Studio request locale SHALL use that value.
2. WHEN locale config is null/empty THEN Studio SHALL use `App::getLocale()`.
3. WHEN the active locale lacks a key THEN system SHALL fall back to the `en` catalog.

**Independent Test**: Unit/feature tests for override vs host locale vs missing key.

---

### P1: Ship en + pt_BR catalogs ⭐ MVP

**User Story**: As a Brazilian operator, I want Portuguese chrome for nav, flashes, and registry labels.

**Acceptance Criteria**:

1. WHEN catalogs are reviewed THEN `lang/en` and `lang/pt_BR` SHALL include `ui`, `flash`, `nodes`, `registry`, and `validation` groups as needed.
2. WHEN locale is `pt_BR` THEN nav titles and primary flashes SHALL be Portuguese.

**Independent Test**: Spot-check keys in both locales.

---

### P1: Blade / Livewire chrome ⭐ MVP

**User Story**: As an author, I want layout nav, breadcrumbs, empty states, CTAs, and flashes translated.

**Acceptance Criteria**:

1. WHEN Studio layout renders THEN rail titles SHALL use translation keys.
2. WHEN Livewire flashes a success/error THEN the message SHALL come from `neuronai-studio::flash.*`.
3. WHEN empty states and primary buttons render THEN copy SHALL use `neuronai-studio::ui.*`.

**Independent Test**: Render layout with `pt_BR` and assert Portuguese in HTML.

---

### P1: Registry display labels ⭐ MVP

**User Story**: As an author, I want node/provider/tool/MCP/RAG driver labels translated in pickers and palette metadata from PHP.

**Acceptance Criteria**:

1. WHEN registries expose labels THEN display strings SHALL resolve via `__('neuronai-studio::nodes.*')` / `registry.*` (or equivalent) at read time.
2. WHEN locale is `en` THEN labels SHALL match previous English meaning.

**Independent Test**: Registry label for `agent` node differs between `en` and `pt_BR`.

---

### P1: Publish translations ⭐ MVP

**User Story**: As a host, I want to publish and customize Studio lang files.

**Acceptance Criteria**:

1. WHEN `php artisan vendor:publish --tag=neuronai-studio-lang` runs THEN files SHALL land under `lang/vendor/neuronai-studio`.
2. WHEN docs list publish tags THEN `neuronai-studio-lang` SHALL be documented.

**Independent Test**: Publish dry-run / docs presence.

---

### P2: React bundle i18n

**User Story**: As an author, I want canvas/chat/forms UI strings to respect the Studio locale.

**Acceptance Criteria**:

1. WHEN bundles mount THEN locale + JSON catalog SHALL be injected from Blade.
2. WHEN a user-facing literal is shown in canvas/chat/forms THEN it SHALL go through `t(key)`.
3. WHEN assets are built THEN dist bundles SHALL include the i18n helper usage.

**Independent Test**: Mount with `pt_BR` catalog; assert a known canvas string.

---

### P3: Artisan command strings

**User Story**: As a CLI user, I want install/export command output in the active locale when catalogs exist.

**Acceptance Criteria**:

1. WHEN commands print user-facing lines THEN they SHALL use `neuronai-studio::commands.*`.

**Independent Test**: Run command with `pt_BR` and assert Portuguese snippet.

---

## Edge Cases

- WHEN config locale is invalid / missing catalog THEN system SHALL fall back to `en` strings.
- WHEN host publishes partial overrides THEN missing keys SHALL fall back to package `en`.
- WHEN JS catalog key is missing THEN `t()` SHALL return the key or EN fallback (documented).

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
|----------------|-------|-------|--------|
| I18N-01 | Package translation loading | Execute | Pending |
| I18N-02 | Locale resolution | Execute | Pending |
| I18N-03 | Ship en + pt_BR catalogs | Execute | Pending |
| I18N-04 | Blade / Livewire chrome | Execute | Pending |
| I18N-05 | Livewire flashes | Execute | Pending |
| I18N-06 | Registry display labels | Execute | Pending |
| I18N-07 | Publish tag + docs | Execute | Pending |
| I18N-08 | PHPUnit locale + keys | Execute | Pending |
| I18N-09 | JS JSON catalogs + `t()` | Execute | Pending |
| I18N-10 | Blade inject locale/catalog | Execute | Pending |
| I18N-11 | Replace JS literals | Execute | Pending |
| I18N-12 | Rebuild dist assets | Execute | Pending |
| I18N-13 | Artisan command strings | Execute | Pending |

**Coverage:** 13 total

---

## Success Criteria

- [ ] `APP_LOCALE=pt_BR` (or config override) shows Portuguese Studio chrome (P1)
- [ ] `en` remains default-quality catalog
- [ ] Tests cover resolver + sample keys
- [ ] P2/P3 complete per tasks
