# Shared Plugin Packages — Tasks

**Status:** Done (P1)  
**Design:** [design.md](./design.md) · **Spec:** [spec.md](./spec.md) · **Context:** [context.md](./context.md)  
**Gate:** `./vendor/bin/phpunit --filter='Skill|Plugin'` ✅  
**Branch:** `feat/shared-plugin-packages` → `v3.1.x`

---

## Execution Plan

```
T1 → T2 → T3 → T4 → T5 → T6 → T7 → T8 → T9
```

---

## Task Breakdown

### T1: Schema + models ✅
### T2: SkillContent + catalog entry ✅
### T3: SkillRepository skill:pkg refs ✅
### T4: PluginPackageRegistry ✅
### T5: Thin catalog materializer ✅
### T6: Installer catalog → package ✅
### T7: Agent binder pkg refs ✅
### T8: Tests ✅ (`PluginPackageSharingTest` + updated Plugin/ConnectorsLayout)
### T9: Docs + ROADMAP/STATE ✅

---

## Traceability

| ID | Tasks | Status |
|----|-------|--------|
| SPP-01 | T1, T4, T8 | Verified |
| SPP-02 | T5, T6, T8 | Verified |
| SPP-03 | T2, T3, T7, T8 | Verified |
| SPP-04 | T5, T8 | Verified |
| SPP-05 | T5, T8 | Verified |
| SPP-10 | T5, T6 | Verified |
