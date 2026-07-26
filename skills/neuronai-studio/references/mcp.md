# MCP servers

## What

Model Context Protocol connectors (stdio or HTTP). Config presets in `neuronai-studio.php` → `mcp_servers`; runtime records in Studio UI. Tools resolved via `McpToolResolver` and bound to agents / MCP workflow nodes.

## Workflow

1. Create MCP server in Studio or start from config preset
2. Configure transport (stdio command or HTTP URL)
3. Test discovery in edit UI
4. Bind to agent and/or use **mcp** node in workflows

## Checklist

- [ ] Stdio: binary/`npx` available to PHP process user
- [ ] HTTP: URL reachable from app server
- [ ] Agent binding verified after discovery refresh

## Documentation (canonical)

- [Overview](../../../docs/guides/mcp-servers/overview.md)
- [Stdio & HTTP](../../../docs/guides/mcp-servers/stdio-and-http.md)
- [Agent Binding](../../../docs/guides/mcp-servers/agent-binding.md)
