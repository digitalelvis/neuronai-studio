# Plugins (closed catalog)

NeuronAI Studio supports **host-controlled connector packs** in Claude plugin format (`.claude-plugin/plugin.json`, `skills/`, optional `.mcp.json`).

## Host policy

Configure in `config/neuronai-studio.php` or `.env`:

| Key | Default | Meaning |
|-----|---------|---------|
| `NEURONAI_STUDIO_PLUGINS_ENABLED` | `true` | Show Plugins UI and allow install |
| `NEURONAI_STUDIO_PLUGINS_MODE` | `closed` | `closed` = package catalog + `plugins.catalog` only; `allowlist` adds `plugins.allowlist` |
| `NEURONAI_STUDIO_PLUGINS_STDIO` | `false` | Allow stdio MCP connectors from plugins |

```php
'plugins' => [
    'enabled' => true,
    'mode' => 'closed',
    'stdio' => false,
    'allowlist' => [],
    'catalog' => [],
    'catalog_paths' => [],
],
```

## Install flow

1. Open **Plugins** in Studio.
2. **Add** a listing from the closed catalog (built-in: `demo-assistant`).
3. Open **Manage** → map env keys to `var:NAME` credentials.
4. On an **Agent**, select installed plugins; skills + MCP sync automatically.

## Package catalog

Official listings ship in `resources/plugins/marketplace.json`. Hosts can add paths via `plugins.catalog` or `plugins.catalog_paths`.

## Security

- No third-party PHP in plugins.
- Stdio MCP requires host allowlist + `plugins.stdio`.
- Open marketplace mode is not supported in this release.
