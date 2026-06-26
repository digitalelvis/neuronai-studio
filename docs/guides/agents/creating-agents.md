# Creating Agents

The agent editor lets you configure provider, model, instructions, and tool bindings through a React form embedded in Livewire.

## Create a new agent

1. Navigate to **Agents** → **Create Agent**
2. Fill in the form fields
3. Save

<!-- SCREENSHOT: agents-form -->
> **Screenshot pending:** Agent editor with provider, model, instructions, and tool bindings.
>
> Asset path: `docs/assets/screenshots/agents-form.png`
> Capture: `/neuronai-studio/agents/create` or edit page — dark theme, 1440×900

![Agent form](../assets/screenshots/agents-form.png)

## Form fields

| Field | Description |
|-------|-------------|
| **Name** | Display name shown in lists and workflow nodes |
| **Provider** | LLM provider (OpenAI, Anthropic, Gemini, Ollama) |
| **Model** | Model ID for the selected provider |
| **Instructions** | System prompt — defines agent behavior and constraints |

Providers and models come from `config/neuronai-studio.php`. Credentials are read from `config/neuron.php`.

## Tool bindings

Bind tools to give your agent capabilities beyond text generation.

### Binding format

Each binding references a tool by `ref`:

```json
{
  "ref": "db:1",
  "only": ["search"],
  "exclude": []
}
```

| Prefix | Meaning | Example |
|--------|---------|---------|
| `db:{id}` | Database tool definition | `db:3` |
| `toolkit:{key}` | Built-in Neuron toolkit | `toolkit:calculator` |
| `class:{fqn}` | Scanned PHP Tool class | `class:App\\Neuron\\Tools\\WeatherTool` |
| `mcp:{server}:{tool}` | MCP-exposed tool | `mcp:filesystem:read_file` |

### Filter tools

- **only** — expose only listed tool names from a toolkit or MCP server
- **exclude** — hide specific tool names

Browse available tools in the [Tool Registry](../tools/registry-and-codegen.md).

## MCP server bindings

Agents can bind entire MCP servers. Configure MCP servers first, then add bindings in the agent form. See [Agent Binding](../mcp-servers/agent-binding.md).

## Provider configuration

Add or customize providers in `config/neuronai-studio.php`:

```php
'providers' => [
    'openai' => [
        'label' => 'OpenAI',
        'models' => ['gpt-4o', 'gpt-4o-mini'],
    ],
],
```

Set defaults with `NEURONAI_STUDIO_DEFAULT_PROVIDER` and `NEURONAI_STUDIO_DEFAULT_MODEL`.

## Export

Export the agent to a PHP class:

```bash
php artisan neuronai-studio:export agent {id}
```

See [Export & Production](../export-and-production.md).

## Next steps

- [Playground & Threads](playground-and-threads.md) — test your agent
- [Attachments](attachments.md) — multimodal messages
- [Tools Overview](../tools/overview.md)
