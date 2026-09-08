# Agent Skills — Tasks

## ASK-T1 — SDD + schema

- Migration `skill_definitions`, column `agent_definitions.skills`
- Model `SkillDefinition`, `StudioTables` update
- **Done when:** MigrationTest passes

## ASK-T2 — Runtime core

- `SkillParser`, `SkillRepository`, `SkillResolver`, `SkillCatalog`, `SkillCatalogInjector`
- `ActivateSkillTool`, `ReadSkillResourceTool`, `SkillRuntime` interface
- Wire `AgentRunner`, `DynamicAgent`, `AgentNodeExecutor`
- **Done when:** PHPUnit skill runtime tests green

## ASK-T3 — Graph + validator

- `GraphContext::skillBindingsFor`, exclude `skills` from control flow
- `validateSkillBindingEdges`, skill node flow guard
- `SkillNodeExecutor` (binding-only)
- **Done when:** GraphContext/Validator tests green

## ASK-T4 — Authoring UI

- Livewire Skills Index/Edit, routes, nav, i18n
- Agent form multi-select skills
- **Done when:** CRUD saves valid skill

## ASK-T5 — Canvas

- Palette Skills catalog, `skill` node type, agent `skills` handle
- `skillsForCanvas` in Editor
- **Done when:** Drag skill → edge to agent skills pin validates

## ASK-T6 — Export + docs + tests

- `AgentExporter` skill snapshot
- `docs/guides/agents/skills.md`, update neuronai-studio skill router
- Full test suite + browser smoke
- **Done when:** Export contains SKILL.md; docs linked
