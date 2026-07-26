# AI-Assisted Development

When working with AI coding assistants (Cursor, Claude Code, OpenCode, and similar tools), point them at the NeuronAI Studio **Agent Skill** so suggestions match Studio APIs, config, and workflows — with fewer hallucinations.

NeuronAI Studio follows the [Agent Skills](https://agentskills.io/) convention (same idea as [Neuron AI agentic development](https://docs.neuron-ai.dev/overview/agentic-development)).

## Agent Skill location

After Composer install, the skill lives at:

```text
vendor/digitalelvis/neuronai-studio/skills/
└── neuronai-studio/
    ├── SKILL.md
    └── references/
        ├── setup.md
        ├── agents.md
        ├── workflows.md
        ├── tools.md
        ├── knowledge.md
        ├── mcp.md
        ├── export.md
        └── extending.md
```

Canonical product docs (linked from the skill) are in:

```text
vendor/digitalelvis/neuronai-studio/docs/
```

The skill stays small on purpose: the assistant should open the matching `references/*.md` file, then read the linked guide under `docs/` when more detail is required.

## How to install

### Claude Code

From your Laravel application root:

```bash
npx skills add ./vendor/digitalelvis/neuronai-studio/skills
```

Skills are typically installed as a symlink, so they stay current when you update the package with Composer.

### Cursor

In **Cursor Settings → Features → Docs**, add the skill directory:

```text
vendor/digitalelvis/neuronai-studio/skills
```

You can also add a project rule that tells the agent to use `vendor/digitalelvis/neuronai-studio/skills/neuronai-studio/` (and to read `docs/` links when context is insufficient).

### Other AI tools

Any assistant that supports the Agent Skills specification can use this skill. Check your tool’s docs for adding a custom skills directory.

## Neuron AI skills (runtime PHP)

For **pure Neuron AI** PHP (agents, tools, workflows outside Studio UI), also install or reference:

```text
vendor/neuron-core/neuron-ai/skills/
```

See [Neuron AI — Agentic Development](https://docs.neuron-ai.dev/overview/agentic-development).

| Concern | Skills |
|---------|--------|
| Studio install, canvas, playground, DB definitions, export | `digitalelvis/neuronai-studio` → `skills/neuronai-studio` |
| Neuron `Agent` / `Workflow` / Tool PHP APIs | `neuron-core/neuron-ai` → `skills/*` |

## Recommended usage

1. Ask the assistant to follow the **neuronai-studio** skill for Studio tasks.
2. Expect it to open `references/<domain>.md` for the domain (agents, workflows, etc.).
3. If answers are thin or uncertain, have it **read** the markdown files under `vendor/digitalelvis/neuronai-studio/docs/` linked from that reference.

## Related

- [Installation](../getting-started/installation.md)
- [Documentation index](../SUMMARY.md)
