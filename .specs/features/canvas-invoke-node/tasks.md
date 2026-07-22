# Canvas Invoke Node — Tasks

**Spec**: [spec.md](./spec.md)  
**Status**: ✅ Complete on `v0.9.x`  
**Linha**: `v0.9.x` · **Branch**: `feat/canvas-invoke`  
**Design**: skipped — inline design decisions noted per task.

---

## Execution Plan

```
INV-T1 → INV-T2 → INV-T3
INV-T1 → INV-T4 [P]
INV-T2, INV-T4 → INV-T5
INV-T3, INV-T5 → INV-T6
```

---

### INV-T1 — Config `invoke_hooks` + node type meta (INV-01, INV-02)

**What**: Add fail-closed allowlist config and palette meta for `invoke`.  
**Where**: `config/neuronai-studio.php`  
**Inline design**: key `invoke_hooks` = list of FQCN strings, default `[]`; node meta label `Invoke`, category `logic`, icon `code`.  
**Done when**:

- [x] `config('neuronai-studio.invoke_hooks')` returns array
- [x] `node_types.invoke` present with logic category

**Tests**: none (config only)  
**Status**: ✅ Complete

---

### INV-T2 — `InvokeNodeExecutor` + registry wiring (INV-01, INV-02)

**What**: Execute allowlisted hook via container `__invoke(WorkflowState)`; write return to `output_key` (default `invoke_result`); return handle `default`.  
**Where**: `src/Runtime/NodeExecutors/InvokeNodeExecutor.php`, `NeuronAIStudioServiceProvider`  
**Reuses**: `SetStateNodeExecutor` simplicity; `ToolNodeExecutor` `app($class)` pattern  
**Inline design**: reject if not on allowlist / not class / not callable; throw `RuntimeException` with clear message.  
**Done when**:

- [x] Happy path sets state from hook return
- [x] Off-allowlist / empty allowlist / non-callable throws

**Tests**: unit `InvokeNodeExecutorTest`  
**Status**: ✅ Complete

---

### INV-T3 — GraphValidator invoke rules (INV-03)

**What**: Require `hook_class`, on allowlist, `class_exists`, method `__invoke` exists.  
**Where**: `src/Runtime/GraphValidator.php`  
**Done when**:

- [x] Invalid invoke nodes produce validation errors before run

**Tests**: unit / `GraphValidatorTest`  
**Status**: ✅ Complete

---

### INV-T4 — Canvas inspector + defaults (INV-01) [P]

**What**: Inspector fields for `hook_class` and `output_key`; defaults when placing node.  
**Where**: `resources/js/studio-canvas/inspector/NodeConfigForm.jsx`, `nodeUtils.js`, `WorkflowCanvas.jsx`  
**Done when**:

- [x] Invoke branch renders two fields; default `output_key` = `invoke_result`

**Tests**: none required  
**Status**: ✅ Complete

---

### INV-T5 — Codegen (INV-04)

**What**: `InvokeNodeCodeGenerator` emits host call to FQCN; register in registry.  
**Where**: `src/Codegen/NodeCodeGenerators/InvokeNodeCodeGenerator.php`, `NodeCodeGeneratorRegistry.php`  
**Inline design**: emit `app(Hook::class)($state)`; comment that allowlist must stay aligned.  
**Done when**:

- [x] Generator registered; test asserts FQCN appears in output

**Tests**: `InvokeNodeCodegenTest`  
**Status**: ✅ Complete

---

### INV-T6 — Docs + ROADMAP/STATE (INV-05)

**What**: Document invoke vs custom node, `invoke_hooks`, logic nodes guide; mark feature done; leave TraceDetail bridge deferred.  
**Where**: docs + `.specs/project/*`  
**Done when**:

- [x] Docs mention allowlist + contract
- [x] STATE/ROADMAP updated

**Status**: ✅ Complete
