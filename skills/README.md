# NeuronAI Studio Agent Skills

Shipped with the package for AI coding assistants (Cursor, Claude Code, etc.).

## Layout (single skill)

```text
skills/
└── neuronai-studio/
    ├── SKILL.md           # Router: triggers, rules, which reference to read
    └── references/        # One file per domain — keep short
        ├── setup.md
        ├── agents.md
        ├── workflows.md
        ├── tools.md
        ├── knowledge.md
        ├── mcp.md
        ├── export.md
        └── extending.md
```

Do **not** add sibling skill directories under `skills/`. New domains = new `references/*.md` + a row in `SKILL.md` routing table.

## Progressive disclosure

1. `SKILL.md` — always
2. `references/<domain>.md` — when the task matches
3. Linked files under `docs/` — when the reference is not enough (**Read** them; do not invent)

Skills must **not** copy full guides. Condensed workflow + checklist + Documentation links only.

## Sync with documentation

When you change docs that a reference links:

1. Update the matching `references/*.md` checklist/workflow if behavior changed
2. Update `SKILL.md` description/triggers if new user-facing terms appear
3. Add/remove SUMMARY sections → add/remove or retarget a reference file
4. Keep relative links valid from `references/` → `../../../docs/...` and from `SKILL.md` → `../../docs/...`

Map: `docs/SUMMARY.md` domains ↔ `references/*.md` (setup covers getting-started + reference; extending covers `docs/extending/`).

## Consumer install

Documented in [docs/guides/ai-assisted-development.md](../docs/guides/ai-assisted-development.md).

```bash
# Claude Code (from the Laravel app root)
npx skills add ./vendor/digitalelvis/neuronai-studio/skills
```

Vendor path: `vendor/digitalelvis/neuronai-studio/skills/neuronai-studio/`.
