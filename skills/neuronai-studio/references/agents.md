# Agents

## What

DB `agent_definitions`: provider, model, instructions, tool/MCP bindings, optional memory. Run via Playground or as workflow **agent** nodes. Runtime: `AgentRunner` → dynamic Neuron agent.

## Workflow

1. Create/edit agent in Studio (`/neuronai-studio/agents`)
2. Bind tools / MCP / RAG tool as needed
3. Test in Playground (threads)
4. Optional: evaluations suite
5. Optional: export PHP — see [export.md](export.md)

## Checklist

- [ ] Provider + model match `config/neuron.php`
- [ ] Instructions are system prompt only (no invented tools)
- [ ] Tool refs exist in registry / DB / MCP
- [ ] Attachments only if multimodal provider supports them
- [ ] Memory: null inherits global context window

## Routes

| Path | Purpose |
|------|---------|
| `/agents` | List / create |
| `/agents/{id}/edit` | Edit |
| `/agents/{id}/playground` | Chat test |
| `/agents/{id}/evals` | Evaluations |

## Documentation (canonical)

- [Overview](../../../docs/guides/agents/overview.md)
- [Creating Agents](../../../docs/guides/agents/creating-agents.md)
- [Playground & Threads](../../../docs/guides/agents/playground-and-threads.md)
- [Evaluations](../../../docs/guides/agents/evaluations.md)
- [Attachments](../../../docs/guides/agents/attachments.md)
