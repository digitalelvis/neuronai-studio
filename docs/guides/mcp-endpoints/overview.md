# MCP Endpoints (Studio → MCP)

Publish curated **tools**, **toolkits**, **agents**, and **workflows** as an outbound [Model Context Protocol](https://modelcontextprotocol.io) server. External clients (Cursor, Claude Desktop, n8n, MCP Inspector) can discover and call them.

This is the opposite of [MCP Servers](../mcp-servers/overview.md) (inbound connectors).

| Concept | Meaning |
|---------|---------|
| **MCP Servers** (existing) | Studio *consumes* remote MCP tools |
| **MCP Endpoints** (this guide) | Studio *exposes* Studio entities as MCP tools |

## Enable the feature

```env
NEURONAI_STUDIO_MCP_ENDPOINTS_ENABLED=true
# optional:
# NEURONAI_STUDIO_MCP_ENDPOINTS_PREFIX=api/neuronai/mcp
# NEURONAI_STUDIO_MCP_TOOL_TIMEOUT=180
```

Routes load only when `neuronai-studio.mcp_endpoints.enabled` is true (same pattern as stream adapters).

## Create an endpoint

1. Open **MCP Endpoints** in the Studio rail.
2. Create an endpoint (name, description, timeout).
3. On first save, Studio generates an API key — **copy it once**.
4. Open the **Bindings** tab and select tools / toolkits / agents / workflows.
5. For toolkits, optionally set `only` / `exclude` as **comma-separated child tool names** (e.g. `sum,multiply`) — this filters what appears in `tools/list`.
6. Enable the endpoint on the General tab.
7. Open **Connect** for the URL and `mcp.json` snippet.

## How tools map

| Binding | MCP tool(s) | Input |
|---------|-------------|--------|
| Tool (`tool:db:*` / `class:*`) | One tool | `input_schema` / tool properties |
| Toolkit | One MCP tool per child (filtered by only/exclude) | Child properties |
| Agent | One tool | `{ "message": "..." }` |
| Workflow | One tool | `{ "input": { ... } }` and/or top-level state keys |

Agents with `require_tool_approval` cannot be invoked via MCP in this version (clear error).

## Auth

Every request must include the endpoint API key:

- `Authorization: Bearer <key>`, or
- `x-api-key: <key>`

Keys are stored as SHA-256 hashes. Rotate from the General tab when needed.

## Next steps

- [Authentication & Connect](auth-and-connect.md)
- [MCP Servers (inbound)](../mcp-servers/overview.md)
