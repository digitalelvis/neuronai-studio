# AI Nodes

AI nodes invoke language models, agents, tools, and MCP connectors within a workflow graph.

## Agent

**Purpose:** Run a configured studio agent with a templated message.

| Config | Description |
|--------|-------------|
| `agent_id` | Database ID of the agent |
| `message` | Prompt template with `{{state_key}}` placeholders |
| `output_key` | State key for the response (default: `agent_response`) |
| `structured` | When `true`, validate and store typed output instead of plain text |
| `output_class` | FQCN or short name of a PHP output class (required when `structured` is on) |

Example message:

```
Customer inquiry: {{input}}
Previous context: {{rag_context}}
```

<!-- SCREENSHOT: workflows-inspector-agent -->
> **Screenshot pending:** Agent node inspector fields.
>
> Asset path: `docs/assets/screenshots/workflows-inspector-agent.png`
> Capture: Workflow editor with Agent node selected in inspector — dark theme, 1440×900

![Agent node inspector](../../../assets/screenshots/workflows-inspector-agent.png)

## LLM

**Purpose:** Direct LLM call without a full agent definition.

| Config | Description |
|--------|-------------|
| `provider` | LLM provider key |
| `model` | Model ID |
| `prompt` | Prompt template with `{{state_key}}` placeholders |
| `output_key` | State key for the response |
| `structured` | When `true`, validate and store typed output instead of plain text |
| `output_class` | FQCN or short name of a PHP output class (required when `structured` is on) |

Use when you need a one-off LLM step without tool bindings.

## Structured output

Agent and LLM nodes support a **structured output** mode. Instead of storing the model response as a string, the runtime validates the response against a PHP output class and writes a typed array to `output_key`.

Enable structured mode in the node inspector:

1. Turn on **Structured output**
2. Select an **Output class** from the dropdown (scanned from `structured_output_scan_paths`)
3. Set **Output Key** — this becomes the state key for the validated object (e.g. `lead`)

When `structured` is off (default), behavior is unchanged: the node stores plain text at `output_key`.

### Output classes

Output classes are plain PHP classes with public properties annotated with NeuronAI `SchemaProperty`. The studio scans configured paths via `OutputClassRegistry` and exposes them in the inspector picker.

Example class at `app/Neuron/Output/LeadProfile.php`:

```php
use NeuronAI\StructuredOutput\SchemaProperty;

class LeadProfile
{
    #[SchemaProperty(description: 'Lead email address', required: true)]
    public string $email;

    #[SchemaProperty(description: 'Lead tier', required: false)]
    public ?string $tier = null;
}
```

The inspector shows a schema preview (property names, types, required flags) when a class is selected.

### Validation and traces

Structured responses pass through the NeuronAI validator. On success, state receives an associative array:

```json
{ "email": "alice@example.com", "tier": "gold" }
```

On validation failure, the workflow trace marks the step as **failed** and SSE `step_completed` events include `validation_errors` with field-level details. Downstream nodes do not run.

### Routing with Condition nodes

Store structured output under a dedicated key, then branch on nested fields using dot notation in the Condition node's **State Key** (e.g. `lead.tier`). See [State & Conditions](../state-and-conditions.md#conditions-on-structured-objects).

```mermaid
flowchart TD
    Start[Start] --> LLM["LLM (structured → lead)"]
    LLM --> Cond{"Condition lead.tier equals gold?"}
    Cond -->|true| VIP[VIP flow]
    Cond -->|false| Std[Standard flow]
```

Structured mode is compatible with agent tool bindings — the agent still runs with its configured tools, but the final response is validated against the output class.

## Tool

**Purpose:** Invoke a studio or registry tool directly.

| Config | Description |
|--------|-------------|
| `tool_ref` | Tool reference (e.g. `db:1`, `toolkit:calculator`) |
| `input` | Input template or JSON with `{{state_key}}` placeholders |
| `output_key` | State key for the result (default: `tool_result`) |

## MCP

**Purpose:** Call a tool exposed by an MCP server.

| Config | Description |
|--------|-------------|
| `server_id` | MCP server database ID |
| `tool_name` | Tool name from MCP discovery |
| `input` | Arguments template |
| `output_key` | State key for the result (default: `mcp_result`) |

## RAG

**Purpose:** Retrieval-augmented generation step.

| Config | Description |
|--------|-------------|
| `output_key` | State key for retrieved context |

> **Note:** RAG node execution is a placeholder in the current studio runtime. Configure the node structure for future RAG integration or export to PHP where full RAG pipelines are implemented.

## AI node comparison

| Node | Tools | Agent config | Best for |
|------|-------|--------------|----------|
| Agent | Via agent bindings | Required | Multi-turn agent with tools |
| LLM | No | No | Simple text generation |
| Tool | Single tool | No | Deterministic tool call |
| MCP | Single MCP tool | No | External MCP capability |
| RAG | No | No | Context retrieval (future) |

```mermaid
flowchart TD
    State[Workflow State] --> Agent[Agent Node]
    State --> LLM[LLM Node]
    State --> Tool[Tool Node]
    State --> MCP[MCP Node]
    Agent -->|output_key| State
    LLM -->|output_key| State
    Tool -->|output_key| State
    MCP -->|output_key| State
```

## Related code

- `AgentNodeExecutor`, `LlmNodeExecutor`, `ToolNodeExecutor`, `McpNodeExecutor`, `RagNodeExecutor`
- `StructuredOutputResolver`, `OutputClassRegistry`, `AgentRunner::structuredInline`

## See also

- [Creating Agents](../../agents/creating-agents.md)
- [State & Conditions](../state-and-conditions.md)
