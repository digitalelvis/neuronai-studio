# Workflow JSON I/O — Specification

## Overview

Import, export, and view/edit the workflow graph JSON directly in the Workflow Editor toolbar and inspector.

## Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| JSON-01 | **Export JSON** button downloads a `.json` file with the full graph (nodes, edges, viewport, version) | P0 |
| JSON-02 | **Import JSON** opens a modal with textarea + file upload | P0 |
| JSON-03 | **JSON** inspector tab (beside Node / Test) to view and edit raw graph | P0 |
| JSON-04 | Import and Apply validate via `GraphValidator` before replacing the canvas | P0 |
| JSON-05 | Import requires confirmation when the canvas has unsaved changes | P1 |
| JSON-06 | Export optionally includes metadata (`name`, `description`, `status`) in `{ meta, graph }` envelope | P1 |

## Acceptance

- Export JSON from toolbar produces a valid graph file
- Import valid JSON updates canvas; Save persists to DB
- Invalid JSON shows validator errors; canvas unchanged
- JSON tab: edit + Apply reflects changes on canvas
- Read-only editor mode disables Import apply and JSON edit
