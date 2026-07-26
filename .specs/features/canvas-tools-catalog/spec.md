# Canvas Tools Catalog — Specification

## Problem Statement

Authors discover tools only after dropping a generic Tool/MCP node and opening a select. There is no palette catalog of available tools/MCP servers, and Tool nodes lack an Actions surface aligned with Neuron `ToolInterface` (name, description, properties).

## Goals

- [ ] Tool Mode toggle uses a Switch control.
- [ ] Palette exposes collapsible **Tools** and **MCP** catalogs; drag pre-fills `tool_ref` / `mcp_server`.
- [ ] Generic Tool/MCP node types remain under Models & Agents (deprecated later).
- [ ] Tool node Actions modal shows ToolInterface schema; builtins read-only; `tool:db:*` editable.

## Out of Scope

| Feature | Reason |
|---------|--------|
| Remove generic Tool/MCP palette items | Deferred deprecation |
| Runtime `controlled_by` / variables / tokens on tool params | Foundation UI only |
| MCP Actions modal | Later |
| Expanding toolkit into per-tool palette rows | Actions modal lists toolkit tools |

**Context:** [context.md](./context.md)

---

## User Stories

### P1: Tool Mode Switch

**Acceptance Criteria:**

1. WHEN Agent is toolable THEN Tool Mode control SHALL be a Switch (not Checkbox).
2. WHEN Switch toggles THEN behavior SHALL match existing `tool_mode` / edge cleanup semantics.

### P1: Tools & MCP palette catalogs

**Acceptance Criteria:**

1. WHEN canvas loads THEN palette SHALL show collapsible **Tools** (non-`mcp:` registry entries) and **MCP** (mcpServers).
2. WHEN author drags Calculator THEN a `tool` node SHALL be created with `tool_ref` pre-filled.
3. WHEN author drags Filesystem THEN an `mcp` node SHALL be created with `mcp_server` pre-filled.
4. WHEN search is used THEN catalogs SHALL filter by label/ref/slug/description.
5. WHEN Models & Agents is shown THEN generic Tool and MCP types SHALL still appear.

### P1: Tool node Actions modal

**Acceptance Criteria:**

1. WHEN Tool node has `tool_ref` THEN Controls SHALL show Actions button opening a modal.
2. WHEN modal opens THEN system SHALL show name, description, and properties aligned with `ToolInterface` / `ToolProperty`.
3. WHEN ref is toolkit THEN modal SHALL list provided tools as selectable actions (read-only schema).
4. WHEN tool is not editable (`editable: false`) THEN fields SHALL be disabled and Save hidden.
5. WHEN tool is `tool:db:*` and editable THEN Save SHALL persist name/description/properties on `ToolDefinition`.
6. WHEN no `tool_ref` THEN Actions SHALL be disabled or inert.

### P2: Schema bootstrap

**Acceptance Criteria:**

1. WHEN Editor renders THEN `toolsForCanvas` SHALL include `editable` and `actions[]` with properties.
2. WHEN toolkit introspection fails THEN `actions` MAY be empty without breaking the palette.

---

## Requirement Traceability

| ID | Requirement |
|----|-------------|
| CTC-01 | Switch for Tool Mode |
| CTC-02 | Tools/MCP palette catalogs + prefilled drag |
| CTC-03 | Keep generic Tool/MCP types |
| CTC-04 | Tool Actions modal (ToolInterface shape) |
| CTC-05 | Disable edit for non-editable tools |
| CTC-06 | Persist edits for `tool:db:*` |
| CTC-07 | Enriched `toolsForCanvas` payload |
