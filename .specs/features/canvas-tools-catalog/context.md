# Canvas Tools Catalog — Context

**Opened:** 2026-07-25  
**Source:** UX request — Switch for Tool Mode, Tool Actions modal, Tools/MCP palette catalogs.

## Locked decisions

| ID | Decision |
|----|----------|
| D1 | Actions schema mirrors Neuron `ToolInterface`: name, description, properties (name/type/description/required). |
| D2 | Toolkit Actions = `provide()` / `tools()` list; single tools = one action. |
| D3 | `editable: true` only for `tool:db:*`; builtins/class/toolkit/mcp are read-only on canvas. |
| D4 | Keep generic Tool/MCP under Models & Agents for now; deprecate later in favor of catalogs. |
| D5 | Drag from catalog seeds `tool_ref` / `mcp_server` on the node. |
| D6 | Exclude `mcp:*` refs from Tools catalog section (MCP section owns them). |
| D7 | Property badge “controlled by caller” is UI foundation; fixed values / variables / tokens deferred. |

## Explicitly deferred

- Deprecating/removing generic Tool/MCP palette rows
- Runtime binding of fixed/variable/token parameters
- MCP Actions modal
- Per-tool palette expansion of toolkits (sum, subtract as separate rows)

## Related

- M10 `canvas-tool-mode` (Agent Tool Mode + ToolExposureModal)
- `ToolRegistry`, `ToolSchemaInspector`, `ToolActionsModal`
