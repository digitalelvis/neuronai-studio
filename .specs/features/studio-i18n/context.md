# Studio i18n — Context

**Gathered:** 2026-07-28  
**Feature line:** `v2.1.x` (AD-029)  
**Status:** Ready for Execute  
**Spec:** [spec.md](./spec.md) · [design](./design.md) · [tasks](./tasks.md)

---

## Feature Boundary

Ship package-level internationalization for NeuronAI Studio UI chrome: English default catalogs plus Portuguese (`pt_BR`) as the primary demand locale. Locale follows the host Laravel app (`App::getLocale`) with optional package config override; no in-Studio language switcher.

**MVP phases:** P1 Blade/Livewire/PHP flashes + registry labels; P2 React bundles; P3 Artisan commands.

---

## Implementation Decisions (locked)

### Locale source (1B)

- Follow `App::getLocale()` from the host app.
- Optional override: `config('neuronai-studio.locale')` when non-empty.
- Fallback catalog: `en` when a key/locale is missing.

### Scope (2A)

- **P1:** Blade + Livewire + flash + validation attributes + node/provider/tool/MCP/RAG display labels (`en` + `pt_BR`).
- **P2:** JS (canvas, chat, forms) via JSON catalogs + Blade injection.
- **P3:** Artisan command strings via `neuronai-studio::commands.*`.

### Locale code

- Portuguese catalog is **`pt_BR`** (not `pt`).

### Out of boundary

- No UI language picker.
- No translation of agent prompts / template instruction bodies.
- No extra locales beyond `en` / `pt_BR` in this feature.
- Do not permanently mutate host locale outside Studio request when using override.

---

## Deferred Ideas

- Additional locales (es, fr, …)
- In-Studio language switcher (session/cookie)
- Template `meta.name` / product content localization
- RTL / locale-specific date formatting polish
