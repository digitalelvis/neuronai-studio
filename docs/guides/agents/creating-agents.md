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

![Agent form](../../assets/screenshots/agents-form.png)

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

## Output classes

Workflow **Agent** and **LLM** nodes can request typed responses using PHP output classes — the same `SchemaProperty` pattern used by NeuronAI structured output. Classes are shared between agent playground exports and workflow nodes; define them once under your export path and reference them from the canvas.

### Define a class

Place classes under `app/Neuron/Output/` (or any path listed in `structured_output_scan_paths`):

```php
namespace App\Neuron\Output;

use NeuronAI\StructuredOutput\SchemaProperty;

class LeadProfile
{
    #[SchemaProperty(description: 'Lead email address', required: true)]
    public string $email;

    #[SchemaProperty(description: 'Lead tier', required: false)]
    public ?string $tier = null;
}
```

Requirements:

- Public properties with at least one `SchemaProperty` attribute
- Non-abstract classes only
- Namespace must match `export_namespace` when under `export_path`

The studio discovers classes via `OutputClassRegistry` and lists them in the workflow node inspector. Short names (e.g. `LeadProfile`) resolve to the full class name at runtime.

### Use in workflows

In the workflow editor, enable **Structured output** on an Agent or LLM node and pick the output class. The validated array is written to the node's `output_key` — branch on fields with dot notation in Condition nodes. See [AI Nodes — Structured output](../workflows/node-types/ai-nodes.md#structured-output) and [State & Conditions](../workflows/state-and-conditions.md#conditions-on-structured-objects).

### Export

Workflow export (`php artisan neuronai-studio:export workflow {id}`) emits `structuredInline()` calls and imports for referenced output classes. Agent export follows the same pattern when structured mode is configured on agent nodes in the graph.

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
