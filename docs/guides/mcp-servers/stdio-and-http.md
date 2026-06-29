# MCP Stdio & HTTP

NeuronAI Studio supports two MCP transport types: **stdio** (local process) and **HTTP** (remote endpoint).

## Stdio transport

Spawns a local process that communicates over stdin/stdout.

### Configuration

| Field | Description |
|-------|-------------|
| `command` | Executable (must be in allowlist) |
| `args` | Command arguments array |
| `env` | Optional environment variables |

Example — filesystem server:

```php
'filesystem' => [
    'transport' => 'stdio',
    'command' => 'npx',
    'args' => ['-y', '@modelcontextprotocol/server-filesystem', storage_path('app')],
],
```

### Stdio allowlist

Only commands in `mcp_stdio_allowlist` can be executed:

```php
'mcp_stdio_allowlist' => [
    'npx', 'node', 'python', 'python3', 'uv', 'uvx',
],
```

An empty allowlist allows all commands (not recommended in production).

## HTTP transport

Connects to a remote MCP server over HTTP.

### Configuration

| Field | Description |
|-------|-------------|
| `url` | MCP endpoint URL |
| `token_env` | Environment variable name for bearer token |

Example:

```php
'telescope' => [
    'transport' => 'http',
    'url' => env('TELESCOPE_MCP_URL'),
    'token_env' => 'TELESCOPE_MCP_TOKEN',
],
```

```env
TELESCOPE_MCP_URL=http://127.0.0.1:8000/telescope/mcp
TELESCOPE_MCP_TOKEN=your-token
```

## Test tool discovery

In the MCP server editor, use **Test Discovery** to list available tools from the server before binding to agents.

<!-- SCREENSHOT: mcp-servers-edit -->
> **Screenshot pending:** MCP server editor with test discovery.
>
> Asset path: `docs/assets/screenshots/mcp-servers-edit.png`
> Capture: MCP server edit page — dark theme, 1440×900

![MCP server editor](../../assets/screenshots/mcp-servers-edit.png)

## Transport comparison

| Aspect | Stdio | HTTP |
|--------|-------|------|
| Deployment | Same machine as Laravel | Remote service |
| Startup | Spawns process per session | HTTP request |
| Security | Command allowlist | Token auth |
| Use case | Local tools (filesystem, CLI) | Shared services (Telescope, SaaS APIs) |

## Workflow MCP node

Workflows can invoke MCP tools directly via the **MCP node**. See [AI Nodes](../workflows/node-types/ai-nodes.md).

## Related code

- `src/MCP/McpStdioTransport.php`
- `src/Registry/McpRegistry.php`
- `src/Runtime/McpToolResolver.php`

## Next steps

- [Agent Binding](agent-binding.md)
- [Security & Access](../security-and-access.md)
