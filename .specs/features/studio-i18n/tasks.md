# Studio i18n — Tasks

**Design**: [design.md](./design.md) · **Spec**: [spec.md](./spec.md)  
**Status**: Done — Execute I18N-T1…T7 complete  
**Line**: `v2.1.x` · **Branch**: `feat/studio-i18n` → `v2.1.x`

---

## Execution Plan

```
I18N-T1 (SDD + ROADMAP) → I18N-T2 (plumbing + catalogs)
                              ↓
                    I18N-T3 (Blade/Livewire/flash)
                              ↓
                    I18N-T4 (registries)
                              ↓
                    I18N-T5 (tests + docs)
                              ↓
                    I18N-T6 (JS P2)
                              ↓
                    I18N-T7 (CLI P3)
```

---

### I18N-T1 — Specs + roadmap (docs)

**What**: Write feature SDD + ROADMAP M12 + STATE AD-029.  
**Done when**: Files exist under `.specs/features/studio-i18n/` and M12 listed.  
**Commit**: `docs(i18n): specify studio localization M12`

---

### I18N-T2 — Plumbing + catalogs (I18N-01…03, 07)

**What**: `loadTranslationsFrom`, config `locale`, middleware, `lang/en` + `lang/pt_BR` core groups, publish tag.  
**Where**: ServiceProvider, config, middleware, `lang/**`  
**Done when**: Keys resolve; publish tag registered.  
**Commit**: `feat(i18n): add locale middleware and translation catalogs`

---

### I18N-T3 — Blade / Livewire / flash (I18N-04, 05)

**What**: Replace chrome strings with `__('neuronai-studio::…')`.  
**Where**: layouts, livewire views/components, Livewire PHP flashes  
**Done when**: Nav/empty/CTA/flash use keys.  
**Commit**: `feat(i18n): wire Blade and Livewire chrome translations`

---

### I18N-T4 — Registry labels (I18N-06)

**What**: Translate display labels at registry read time.  
**Where**: `src/Registry/*`, nodes/registry lang groups  
**Done when**: Labels differ for pt_BR vs en.  
**Commit**: `feat(i18n): translate registry and node display labels`

---

### I18N-T5 — Tests + docs (I18N-07, 08)

**What**: PHPUnit + publish-tags/install docs.  
**Gate**: `./vendor/bin/phpunit tests/I18n`  
**Commit**: `test(i18n): cover locale resolution and document lang publish`

---

### I18N-T6 — JS bundles (I18N-09…12)

**What**: JSON catalogs, `t()`, Blade inject, extract strings, rebuild dist.  
**Commit**: `feat(i18n): localize studio canvas chat and forms bundles`

---

### I18N-T7 — Artisan (I18N-13)

**What**: `commands.php` catalogs + wrap command output.  
**Commit**: `feat(i18n): localize artisan command output`
