# Agent Skills Context

**Gathered:** 2026-09-05
**Spec:** `.specs/features/agent-skills/spec.md`
**Status:** Ready for design

---

## Feature Boundary

Runtime Agent Skills for Studio agents: author skills in DB, bind on Agent/canvas, progressive disclosure via Studio-owned tools until Neuron AI ships native Skills API.

---

## Implementation Decisions

### Runtime strategy

- Studio-owned `SkillParser` + `ActivateSkillTool` / `ReadSkillResourceTool` now.
- `SkillRuntime` interface for future Neuron `addSkill` swap.

### Canvas UX

- Separate **skills** handle on Agent (distinct from **tools** cyan pin).
- Skill nodes are binding-only; validator rejects skill nodes in control-flow path.

### Security

- No execution of `scripts/` in MVP.
- `read_skill_resource` allowlists paths under stored `references/` only.

### Tenancy

- Same pattern as `ToolDefinition`: global catalog + tenant override via M18 scopes.

---

## Deferred Ideas

- Import from filesystem / `npx skills add`
- Execute skill scripts with sandbox
- Neuron native Skills when PR #570 merges
