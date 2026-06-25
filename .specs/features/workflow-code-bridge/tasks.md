# Workflow Code Bridge — Tasks

## Phase 2a — Contract + Exporter

- [ ] T-01 Create `Contracts/StudioWorkflow.php`
- [ ] T-02 Rewrite `WorkflowExporter` + stub for round-trip graph
- [ ] T-03 Add `WorkflowExporterTest`

## Phase 2b — Registry + Importer

- [ ] T-04 Add config `workflow_scan_paths`, `workflow_json_paths`
- [ ] T-05 Create `WorkflowRegistry`
- [ ] T-06 Create `WorkflowClassImporter`
- [ ] T-07 Add `WorkflowRegistryTest`, `WorkflowClassImporterTest`

## Phase 2c — UI + Shadow record

- [ ] T-08 Migration: `class_path`, `source`, `locked` on `workflow_definitions`
- [ ] T-09 Update `WorkflowDefinition` model
- [ ] T-10 `Editor::mountFromCodeClass`, `mountFromJsonRef`, shadow upsert
- [ ] T-11 Merge registry into `Workflows/Index` + blade
- [ ] T-12 Preview route `workflows/preview`
- [ ] T-13 `importToStudio` action on Index

## Phase 2d — Demo

- [ ] T-14 Add `DemoWorkflow.php` in agent-builder-demo
- [ ] T-15 Update broken `Test2Workflow.php` with comment or remove

## Verification

- [ ] T-16 Full PHPUnit suite green
- [ ] T-17 Build canvas bundle
