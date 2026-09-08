# Agent skills (runtime)

## What

DB `skill_definitions`: agentskills.io packages with full file tree (`references/`, `scripts/`, `assets/`), gallery fields (`category`, `cover_image`, `display_name`), and source (`studio` | `upload` | `github`).

Progressive disclosure:

1. Catalog snippet in instructions
2. `activate_skill` → body + file listing
3. `read_skill_file` → resources
4. `run_skill_script` → sandbox (when `skills.execution.enabled`)

**Not** Cursor coding skills (`skills/neuronai-studio/`).

## Authoring UX

- Gallery: search, category filters, cover cards
- Upload `.zip` / `.skill`
- Import GitHub tree URL
- Show page: file tree sidebar + Markdown/code preview

## Routes

| Path | Purpose |
|------|---------|
| `/skills` | Gallery |
| `/skills/create` | Create |
| `/skills/{id}` | Viewer |
| `/skills/{id}/edit` | Edit |

## Documentation

- [Agent Skills](../../../docs/guides/agents/skills.md)
