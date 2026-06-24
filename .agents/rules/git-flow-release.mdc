---
trigger: model_decision
description: Git branch and commit conventions and governance
---

# Repository Governance & Git Flow Regulation

## 1. Branching Model (Release Branches Strategy)

**Strict Prohibition:** Never develop features or request Merges (PRs) directly into the `main` branch!

### Core Branches

- **`main`**: **Production-Ready.** A clean mirror of the current published package on NPM. Only merges from release branches (`vX.Y.x`) or `hotfix/` are permitted. Each merge must be tagged with a version.
- **`vX.Y.x` (Development Main-lines)**: These function as the "develop" branches for specific release series (e.g., `v1.0.x`, `v1.1.x`). They are the primary targets for all new features and improvements within that version's scope.

### Supporting Branches (Ephemeral)

- **`feat/`**: Branched from a specific `vX.Y.x`. Must be atomic and focused. Returns to the same `vX.Y.x` via Pull Request.
- **`fix/`**: Branched from `vX.Y.x` to resolve bugs identified during the development cycle.
- **`hotfix/`**: **Emergency only.** Branched directly from `main` to fix production errors. Must be merged back into `main` and simultaneously back-ported to all active `vX.Y.x` branches.

---

## 2. Commit Standards (Conventional Commits)

Strict adherence to the [Conventional Commits 1.0.0](https://conventionalcommits.org) specification is mandatory for automated versioning via `release-it`.

**Type Reference Table:**

| Type       | Purpose                                               | Impact        |
| :--------- | :---------------------------------------------------- | :------------ |
| `feat`     | New rules, engines, stacks, or capabilities           | Minor Version |
| `fix`      | Bug fixes, memory leaks, or CLI behavior corrections  | Patch Version |
| `refactor` | Code changes that neither fix a bug nor add a feature | None          |
| `docs`     | Updates to README, `.specs`, or SDD Skills            | None          |
| `chore`    | Internal configs, dependencies, or governance updates | None          |
| `perf`     | Performance improvements                              | Patch/Minor   |
| `test`     | Adding or correcting isolated Unit Tests in `tests/`  | None          |

**Breaking Changes:** Append `!` after the type/scope (e.g., `feat(api)!:`) and include the `BREAKING CHANGE:` footer.

---

## 3. Commit Format

**Structure:**

    <type>(<scope>): <description>

    [optional body]

    [optional footer(s)]

- **Imperative mood**: Use "add", not "added" or "adds".
- **Atomic**: One task per commit.
- **Scope**: Lowercase module name (e.g., `auth`, `engine`, `cli`).

---

**This regulation is mandatory. Failure to meet these criteria will result in the abortion of any repository artifact generation.**
