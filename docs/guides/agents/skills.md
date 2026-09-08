# Agent Skills

Agent skills are reusable instruction packages stored in the Studio catalog and bound to agents at design time. They follow the [Agent Skills](https://agentskills.io/specification) format (`SKILL.md` with YAML frontmatter) and use **progressive disclosure** at runtime so the system prompt stays small.

> **Not the same as coding assistant skills.** The Studio nav item **Skills** manages runtime agent skills. Cursor/Claude skills under `skills/neuronai-studio/` help you build the Studio itself — see [AI-Assisted Development](../ai-assisted-development.md).

## What skills do

1. **Discovery** — attached skills appear in the agent system prompt as `name` + `description` only (~100 tokens each).
2. **Activation** — when the model decides a skill applies, it calls `activate_skill(name)` and receives the full `SKILL.md` body.
3. **Resources** — `read_skill_file` / `read_skill_resource` load files under `references/`, `scripts/`, or `assets/`.
4. **Scripts** (optional) — when `skills.execution.enabled` is true, `run_skill_script` executes allowlisted scripts under `scripts/` in a sandbox.

## Catalog gallery

Navigate to **Skills** for a card gallery:

- Search by name / description
- Filter by **category** (a skill can have multiple categories)
- Optional **cover image** on each card
- Actions: **Create**, **Upload** (`.zip` / `.skill`), **Import from GitHub**

Click a card to open the **skill viewer**: two columns with the file tree on the left and formatted Markdown (or code) on the right.

## Create or import

### Create in Studio

1. **Skills** → Create my own → New skill
2. Set display name, slug, description, category, body
3. Add files under `references/`, `scripts/`, or `assets/`
4. Save

### Upload archive

Upload a `.zip` or `.skill` file with `SKILL.md` at the root (or a single root folder containing it). Frontmatter must include `name` and `description`.

### Import from GitHub

Paste a repository URL or a tree path, for example:

`https://github.com/anthropics/claude-plugins-official/tree/main/plugins/claude-code-setup/skills/claude-automation-recommender`

Public repos work without a token. Set `NEURONAI_STUDIO_GITHUB_TOKEN` (or `GITHUB_TOKEN`) for higher rate limits.

## Skill viewer

The show page displays:

- Header: title, category, source, description, cover
- **Sidebar** file tree (`SKILL.md`, `references/`, `scripts/`, `assets/`)
- Preview: YAML frontmatter + rendered Markdown, or code/binary notice

## Bind skills to an agent

### Agent editor

1. **Agents** → edit → **Skills** tab
2. Multi-select catalog skills
3. Save and test in Playground

Bindings: `[{ "ref": "skill:db:12" }]` on `agent_definitions.skills`.

### Workflow canvas

Skill node → agent **skills** handle (violet). Binding-only; not control-flow.

## Runtime tools

| Tool | Purpose |
|------|---------|
| `activate_skill` | Load full instructions + list available files |
| `read_skill_file` | Read `references/`, `scripts/`, or `assets/` |
| `read_skill_resource` | Alias of `read_skill_file` (compat) |
| `run_skill_script` | Execute `scripts/*.{sh,py,js}` when execution is enabled |

### Script execution config

```php
// config/neuronai-studio.php → skills.execution
'enabled' => env('NEURONAI_STUDIO_SKILLS_EXECUTION', env('APP_ENV') === 'local'),
'timeout_seconds' => 30,
```

Scripts run in a temp workspace materializing only that skill’s files. External paths (e.g. `../../core/...`) are **not** available — return clear stderr errors.

## Export

Snapshots include the full tree:

```text
app/Neuron/skills/{slug}/
  SKILL.md
  scripts/...
  references/...
  assets/...
```

## Tenancy

Same rules as tools: global skills visible; tenant isolation on reads/writes.

## Related docs

- [Creating Agents](creating-agents.md)
- [Database schema](../../reference/database-schema.md)
- [Export & Production](../export-and-production.md)
