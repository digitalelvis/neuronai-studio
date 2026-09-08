# Agent Skills — Specification

## Problem Statement

Studio agents only receive static instructions and tools. Authors cannot attach reusable instruction packages (Agent Skills / agentskills.io) with progressive disclosure at runtime.

## Goals

- [ ] CRUD for `SkillDefinition` records (SKILL.md body + references).
- [ ] Bind skills on Agent form and canvas (`skills` handle).
- [ ] Runtime: discovery metadata in instructions + `activate_skill` / `read_skill_resource` tools.

## Out of Scope

| Feature | Reason |
|---------|--------|
| Execute `scripts/` | Security; P2 |
| Wait for neuron-ai PR #570 | Studio-owned adapter |
| Filesystem-only skills | Tenancy |
| Marketplace | Separate product |

**Context:** [context.md](./context.md)

---

## User Stories

### P1: Skill CRUD

**Acceptance Criteria:**

1. WHEN author saves a skill THEN system SHALL validate agentskills.io `name` and `description`.
2. WHEN skill is stored THEN `slug` SHALL match `name` constraints and be unique per tenant scope.

### P1: Agent binding

**Acceptance Criteria:**

1. WHEN author selects skills on Agent form THEN `agent_definitions.skills` SHALL store `skill:db:{id}` refs.
2. WHEN canvas skill node connects to agent `skills` handle THEN runtime SHALL merge binding with definition skills.

### P1: Runtime progressive disclosure

**Acceptance Criteria:**

1. WHEN agent has attached skills THEN instructions SHALL list name + description only.
2. WHEN model calls `activate_skill(name)` THEN system SHALL return SKILL.md body for attached skill.
3. WHEN model calls `read_skill_resource(name, path)` THEN system SHALL return allowlisted reference file content.
4. WHEN skill not attached or path invalid THEN tools SHALL return clear errors.

### P2: Canvas palette

**Acceptance Criteria:**

1. WHEN canvas loads THEN palette SHALL show Skills catalog with drag-to-create `skill` node.

---

## Requirement Traceability

| ID | Requirement |
|----|-------------|
| ASK-01 | Skill CRUD + validation |
| ASK-02 | Agent JSON skills binding |
| ASK-03 | Canvas skills handle + node |
| ASK-04 | Runtime catalog injector |
| ASK-05 | activate_skill tool |
| ASK-06 | read_skill_resource tool |
| ASK-07 | Export snapshot |
| ASK-08 | Tenancy isolation |
