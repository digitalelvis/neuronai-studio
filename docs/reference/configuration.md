# Configuration Reference

Complete reference for `config/neuronai-studio.php`. Publish with:

```bash
php artisan vendor:publish --tag=neuronai-studio-config
```

## Routing & auth

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `route_prefix` | `NEURONAI_STUDIO_ROUTE_PREFIX` | `neuronai-studio` | URL prefix for all studio routes |
| `table_prefix` | `NEURONAI_STUDIO_TABLE_PREFIX` | `neuronai_studio_` | Database table prefix |
| `middleware` | — | `['web', 'neuronai-studio.auth']` | Route middleware stack |
| `gate` | — | `viewNeuronAIStudio` | Authorization gate name |

## Export

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `export_namespace` | `NEURONAI_STUDIO_EXPORT_NAMESPACE` | `App\Neuron` | PHP namespace for exported classes |
| `export_path` | `NEURONAI_STUDIO_EXPORT_PATH` | `app/Neuron` | Export directory |

## AI providers

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `providers` | — | openai, anthropic, gemini, ollama | Provider/model picker options |
| `default_provider` | `NEURONAI_STUDIO_DEFAULT_PROVIDER` | `openai` | Default provider in forms |
| `default_model` | `NEURONAI_STUDIO_DEFAULT_MODEL` | `gpt-4o-mini` | Default model in forms |

Credentials are **not** stored here — they come from `config/neuron.php`.

## Chat history

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `chat_history_context_window` | `NEURONAI_STUDIO_CHAT_HISTORY_CONTEXT_WINDOW` | `150000` | Max tokens loaded for agent threads |

## Queue

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `queue` | `NEURONAI_STUDIO_QUEUE` | `default` | Queue name (reserved for future async runs) |
| `queue_connection` | `NEURONAI_STUDIO_QUEUE_CONNECTION` | `null` | Queue connection override |

## Inspector

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `inspector_enabled` | `NEURONAI_STUDIO_INSPECTOR_ENABLED` | `false` | Inspector APM integration (reserved) |

## Tools

| Key | Default | Description |
|-----|---------|-------------|
| `tools` | calculator, calendar | Built-in toolkit registry |
| `tool_scan_paths` | `app/Neuron/Tools` | Paths to scan for PHP Tool classes |

## Structured output

| Key | Default | Description |
|-----|---------|-------------|
| `structured_output_scan_paths` | `{export_path}/Output` when directory exists, else `[]` | Paths to scan for PHP output classes with `SchemaProperty` attributes |

Classes discovered here populate the **Output class** dropdown on Agent and LLM nodes in the workflow canvas. Each path can be absolute or relative to the application base path.

Default behavior:

```php
'structured_output_scan_paths' => is_dir($exportPath.'/Output')
    ? [$exportPath.'/Output']
    : [],
```

Add extra scan paths when output classes live outside the export directory:

```php
'structured_output_scan_paths' => [
    app_path('Neuron/Output'),
    app_path('DTOs/AgentOutput'),
],
```

Classes must have public properties annotated with `NeuronAI\StructuredOutput\SchemaProperty`. Abstract classes and classes without schema properties are ignored.

## Workflows

| Key | Default | Description |
|-----|---------|-------------|
| `workflow_scan_paths` | `app/Neuron`, `app/Neuron/Workflows` | PHP workflow class scan paths |
| `workflow_json_paths` | `workflows/` | JSON workflow import paths |

## Templates

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `templates_enabled` | `NEURONAI_STUDIO_TEMPLATES_ENABLED` | `true` | Enable template browser |
| `template_paths` | — | package `resources/templates/` | Agent/workflow template directories |

## MCP

| Key | Default | Description |
|-----|---------|-------------|
| `mcp_servers` | filesystem, telescope | Config preset MCP servers |
| `mcp_stdio_allowlist` | npx, node, python, etc. | Allowed stdio commands |

## Webhooks

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `webhook_allowed_hosts` | `NEURONAI_STUDIO_WEBHOOK_ALLOWED_HOSTS` | `*` | Host allowlist for webhook tools |
| `webhook_timeout` | `NEURONAI_STUDIO_WEBHOOK_TIMEOUT` | `15` | Request timeout in seconds |

## Node types

| Key | Description |
|-----|-------------|
| `node_types` | Metadata (label, icon, category) for canvas palette |

## Attachments

| Key | Env | Default | Description |
|-----|-----|---------|-------------|
| `attachments.disk` | `NEURONAI_STUDIO_ATTACHMENTS_DISK` | `local` | Storage disk |
| `attachments.path` | `NEURONAI_STUDIO_ATTACHMENTS_PATH` | `neuronai-studio/attachments` | Storage path |
| `attachments.max_size_kb` | `NEURONAI_STUDIO_ATTACHMENTS_MAX_KB` | `10240` | Max upload size |
| `attachments.allowed_mimes` | — | images, audio, video, pdf, text | Allowed MIME types |

## See also

- [Publish Tags](publish-tags.md)
- [Security & Access](../guides/security-and-access.md)
