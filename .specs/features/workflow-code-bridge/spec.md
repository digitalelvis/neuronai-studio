# Workflow Code Bridge — Specification

## Overview

Discover workflows defined in PHP or JSON files in the host app, preview them read-only in the Workflow Editor canvas, run tests via the existing harness, and optionally import into the studio as editable DB workflows.

## Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| CODE-01 | `WorkflowRegistry` discovers `StudioWorkflow` classes in configured scan paths | P0 |
| CODE-02 | Workflows index lists DB + code entries with visual distinction | P0 |
| CODE-03 | Preview opens graph on canvas in read-only mode | P0 |
| CODE-04 | Test harness works in preview via shadow DB record | P0 |
| CODE-05 | Graph re-synced from PHP/JSON source on each preview open | P0 |
| CODE-06 | Export PHP generates faithful round-trip `studioGraph()` | P0 |
| CODE-07 | "Import to Studio" creates editable DB copy | P0 |
| CODE-08 | Neuron native workflows without `studioGraph()` show friendly error | P1 |
| CODE-09 | JSON files in `workflow_json_paths` appear in index | P1 |
| CODE-10 | Tests cover importer, registry, shadow upsert, readonly flags | P0 |

## Acceptance

- Export PHP → class implements `StudioWorkflow` with full graph
- Code workflow appears in index with Code badge
- Preview: read-only canvas + banner with class path
- Open Test runs via SSE on shadow record
- Re-open after PHP edit shows updated graph
- Import to Studio creates independent editable workflow
- Native `NeuronAI\Workflow\Workflow` without bridge shows error, no crash
