# MCP Endpoints — Auth & Connect

## Endpoint URL

Default:

```text
{APP_URL}/api/neuronai/mcp/{slug}
```

Configure the prefix with `NEURONAI_STUDIO_MCP_ENDPOINTS_PREFIX` / `neuronai-studio.mcp_endpoints.route_prefix`.

Methods:

| Method | Purpose |
|--------|---------|
| `POST` | Streamable HTTP JSON-RPC (`initialize`, `tools/list`, `tools/call`, `ping`) |
| `GET` | Short SSE readiness stream (legacy / Inspector helpers) |
| `DELETE` | Terminate MCP session (`Mcp-Session-Id`) |

## Cursor / Claude snippet

From the Connect tab (or equivalent):

```json
{
  "mcpServers": {
    "studio-export": {
      "url": "https://your-app.test/api/neuronai/mcp/studio-export",
      "headers": {
        "Authorization": "Bearer nes_..."
      }
    }
  }
}
```

Clients that only speak stdio can bridge with `mcp-remote` or `mcp-proxy` pointing at the same URL and headers.

## MCP Inspector

```bash
npx @modelcontextprotocol/inspector
```

Use transport compatible with Streamable HTTP / SSE against your endpoint URL and pass the API key header.

## Protocol surface (v1)

| Method | Result |
|--------|--------|
| `initialize` | Server info + `tools` capability; sets `Mcp-Session-Id` |
| `notifications/initialized` | Ack (no body) |
| `tools/list` | Flat list of exposed tools + `inputSchema` |
| `tools/call` | Runs the bound Studio tool / toolkit child / agent / workflow |
| `ping` | Empty result |

Tool failures return MCP tool result with `isError: true` (HTTP 200), not a generic 500, whenever possible.

## Observability

- Tool / toolkit calls create a `StudioThread` + `StudioRun` with `entity_type = McpEndpoint`.
- Agent / workflow bindings reuse `AgentRunner` / `WorkflowRunner` runs as usual.

## Security checklist

- Keep `mcp_endpoints.enabled` false until you intend to expose tools.
- Prefer HTTPS in production.
- Rotate keys when a client is decommissioned.
- Scope bindings narrowly (prefer `only` on toolkits).
- Put host rate-limiting / WAF in front of `api/neuronai/mcp` when public.
