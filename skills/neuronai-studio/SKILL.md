---
name: neuronai-studio
description: >
  Build and configure NeuronAI Studio (Laravel) — agents, workflows, tools, MCP,
  knowledge bases, export to production PHP, and package setup. Use when working
  with NeuronAI Studio, neuronai-studio routes/config, studio canvas, playground,
  agent definitions, workflow graphs, builder/webhook tools, RAG knowledge bases,
  MCP servers in the studio, or exporting App\Neuron classes. For pure Neuron AI
  PHP agents/workflows outside the studio UI, use vendor/neuron-core/neuron-ai/skills/.
---

# NeuronAI Studio

Visual AI agent builder for Laravel. Definitions live in the DB; runtime hydrates Neuron AI; export generates PHP under `app/Neuron`.

## Progressive disclosure (required)

1. Use this file for routing and rules.
2. Read the matching [references/](references/) file for the domain.
3. If still insufficient, **Read** the Documentation links in that reference (`../../docs/...`). Do not invent APIs, config keys, or node types.

## When to use / When to delegate

| Task | Action |
|------|--------|
| Studio UI, install, config, DB definitions, canvas, playground, export | This skill + references |
| Pure Neuron PHP (`Agent`, `Workflow`, tools) without Studio | `vendor/neuron-core/neuron-ai/skills/` (e.g. `neuron-agent-builder`) |
| Both (export then customize PHP) | Studio export ref first, then Neuron skills |

## Route by task

| Task | Read |
|------|------|
| Install, config, publish tags, env, demo app | [references/setup.md](references/setup.md) |
| Agents, playground, threads, evals, attachments | [references/agents.md](references/agents.md) |
| Workflow canvas, nodes, state, HITL, traces | [references/workflows.md](references/workflows.md) |
| Builder/webhook/RAG tools, registry, codegen CLI | [references/tools.md](references/tools.md) |
| Knowledge bases, ingest, vector stores, RAG | [references/knowledge.md](references/knowledge.md) |
| MCP servers, stdio/HTTP, agent binding | [references/mcp.md](references/mcp.md) |
| Export PHP, production CodeGen flags | [references/export.md](references/export.md) |
| Custom nodes, providers, Studio UI contrib | [references/extending.md](references/extending.md) |

## Hard rules

- Prefer studio docs over guessing: package is `digitalelvis/neuronai-studio`.
- Config: `config/neuronai-studio.php` + `config/neuron.php` (providers).
- Default route prefix: `/neuronai-studio`.
- CodeGen/export often gated to `local` — check `codegen.*` flags before advising export.
- Builder tools are for local prototyping; production prefers exported PHP classes or webhooks/MCP.

## Package path (consumer apps)

```text
vendor/digitalelvis/neuronai-studio/skills/neuronai-studio/
vendor/digitalelvis/neuronai-studio/docs/
```

How to install this skill in Cursor/Claude: see [AI-Assisted Development](../../docs/guides/ai-assisted-development.md).
