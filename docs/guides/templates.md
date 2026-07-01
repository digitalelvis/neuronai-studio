# Templates

NeuronAI Studio ships pre-installed **agent** and **workflow** templates as JSON files inside the package. Templates are discovered at runtime and instantiated on demand — nothing is seeded into the database during install.

## Browse and use

1. Open **Templates** in the sidebar (`/neuronai-studio/templates`).
2. Filter by type (Agents / Workflows) or workflow complexity (Basic / Intermediate / Advanced).
3. Click **Use Template** to create an editable Studio record.

- **Agent templates** redirect to the agent editor.
- **Workflow templates** create any required agents first, remap graph references, then redirect to the workflow editor.

Repeating the same workflow template creates a new workflow. Agents referenced by slug are reused when they already exist.

<!-- SCREENSHOT: templates-gallery -->
> **Screenshot pending:** Template browser with type and complexity filters.
>
> Asset path: `docs/assets/screenshots/templates-gallery.png`
> Capture: `/neuronai-studio/templates` — dark theme, 1440×900

![Templates gallery](../assets/screenshots/templates-gallery.png)

## Bundled templates

### Agents

| ID | Name |
|----|------|
| `support-assistant` | Support Assistant |
| `intent-classifier` | Intent Classifier |
| `knowledge-agent` | Knowledge Agent |
| `lead-qualifier` | Lead Qualifier (tools + multimodal) |

### Workflows

| ID | Complexity | Description |
|----|------------|-------------|
| `basic-agent-chat` | Basic | Single agent chat flow |
| `lead-qualification` | Intermediate | LLM extraction + condition branching |
| `lead-qualification-loop` | Intermediate | LLM extraction in a cyclic loop until email found |
| `autonomous-lead-qualification` | Intermediate | Agent + tools + attachments in a loop |
| `support-rag-hitl` | Advanced | Intent routing, RAG, human-in-the-loop |

## Lead Qualification (loop)

Template `lead-qualification-loop` demonstrates cyclic graphs: an LLM extracts lead data, loops until `lead_profile` contains `@` or `max_steps` is reached, then branches to agent follow-up or a missing-email prompt.

Use loops when the same subgraph must run multiple times with shared state. Pair with `max_steps` guardrails — see [Logic Nodes](workflows/node-types/logic-nodes.md).

## Autonomous Lead Qualification

Template `autonomous-lead-qualification` runs the `lead-qualifier` agent inside a loop with **human-in-the-loop**:

1. You send an initial message (optionally with PDF/image attachments).
2. The agent extracts a profile or asks for missing fields (typically email).
3. If email is missing, the workflow **pauses** at a Human node and shows the agent's question in the harness.
4. You reply in the composer; the workflow **resumes**, appends your answer to `lead_message`, and the agent tries again.
5. When `lead_profile` contains `@`, the loop exits and the workflow completes as `qualified`.

Requires cyclic graphs, multimodal attachments (`state.attachments`, `MessageFactory`), and harness resume support.

## File locations

```
resources/templates/agents/*.json
resources/templates/workflows/*.json
```

Configure scan paths in `config/neuronai-studio.php`:

```php
'templates_enabled' => true,

'template_paths' => [
    'agent' => dirname(__DIR__).'/resources/templates/agents',
    'workflow' => dirname(__DIR__).'/resources/templates/workflows',
],
```

## Agent template format

```json
{
  "meta": {
    "id": "support-assistant",
    "name": "Support Assistant",
    "description": "Friendly customer support agent.",
    "category": "support",
    "tags": ["chat", "starter"]
  },
  "definition": {
    "provider": "openai",
    "model": "gpt-4o-mini",
    "instructions": "You are a helpful support assistant...",
    "tools": []
  }
}
```

The `meta.id` value becomes the agent **slug** when installed.

## Workflow template format

```json
{
  "meta": {
    "id": "basic-agent-chat",
    "name": "Basic Agent Chat",
    "description": "Minimal workflow.",
    "complexity": "basic",
    "category": "starter",
    "tags": ["agent", "chat"],
    "agents": ["support-assistant"]
  },
  "graph": {
    "version": 1,
    "nodes": [],
    "edges": [],
    "viewport": { "x": 0, "y": 0, "zoom": 1 }
  }
}
```

### Agent nodes in workflow templates

Use `agent_ref` (template slug) instead of `agent_id`:

```json
{
  "type": "agent",
  "data": {
    "agent_ref": "support-assistant",
    "message": "User message: {{input}}",
    "output_key": "agent_response"
  }
}
```

`TemplateInstaller` resolves `agent_ref` to database IDs before saving. List required agents in `meta.agents`.

### State placeholders

Prompt and message fields support `{{state_key}}` placeholders (for example `{{input}}`, `{{rag_context}}`, `{{lead_profile}}`). These are interpolated at runtime from workflow state. See [State & Conditions](workflows/state-and-conditions.md).

## Adding a new template

1. Add a JSON file under `resources/templates/agents/` or `resources/templates/workflows/`.
2. Ensure `meta.id` is unique and matches the filename slug.
3. For workflows, validate the graph (one start, at least one stop, reachable paths).
4. Clear config cache if paths were customized.

## Related code

- `src/Registry/TemplateRegistry.php` — discovery and metadata
- `src/Services/TemplateInstaller.php` — instantiation and agent remapping
- `src/Http/Livewire/Templates/Index.php` — UI
- `src/Runtime/StateTemplateInterpolator.php` — `{{key}}` substitution at runtime
