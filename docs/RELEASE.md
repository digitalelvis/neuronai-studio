# Release Process

Operational guide for versioning and publishing `digitalelvis/neuronai-studio` on Packagist.

## Overview

| Component | Role |
|-----------|------|
| `release-it` | Semver bump, `CHANGELOG.md`, Git tag, GitHub Release |
| `.github/workflows/release.yml` | Runs `release-it --ci` on every push to `main` (skips `[skip ci]` commits) |
| `RELEASE_TOKEN` (repo secret) | Administrator fine-grained PAT used to push the release commit past the `Protect main` ruleset |
| `.github/workflows/ci.yml` | PHPUnit + frontend build gate on PRs to `main` and `v*.*.x` |
| Packagist | Consumes Git tags (`v1.2.3`) — no `version` field in `composer.json` |

## Day-to-day development

1. Branch from the active feature line (currently `v0.8.x` after `v0.8.0`):

   ```bash
   git checkout v0.8.x
   git pull
   git checkout -b feat/my-feature
   ```

2. Commit using [Conventional Commits](https://conventionalcommits.org) (`feat(studio):`, `fix(canvas):`, etc.).

3. Open a PR targeting the line you branched from. CI must pass before merge.

Patches for the published `0.8` series go to `v0.8.x`. Older patch lines: `v0.7.x` / `v0.6.x`.

## Standard release

1. Ensure the release-candidate line (e.g. `v0.7.x`) is stable and CI is green.

2. Open a PR from that line → `main`. Title example: `release: v0.7.0`.

3. Merge the PR. The Release workflow will:
   - Analyze commits since the last tag
   - Bump `package.json` version (release-it anchor only — package is not published to npm)
   - Update `CHANGELOG.md`
   - Commit `chore(release): X.Y.Z [skip ci]`
   - Create tag `vX.Y.Z`
   - Publish a GitHub Release

4. Packagist picks up the new tag automatically (when auto-update is enabled).

5. Back-merge `main` into the active lines to sync the changelog:

   ```bash
   git checkout v0.7.x
   git pull
   git merge main
   git push
   # Also back-merge into v0.6.x when that patch line is still active
   ```

## Hotfix (production emergency)

1. Branch from `main`:

   ```bash
   git checkout main
   git pull
   git checkout -b hotfix/critical-fix
   ```

2. Fix, commit (`fix(...):`), open PR to `main`, merge.

3. The Release workflow creates a patch release automatically.

4. Backport to active development branches:

   ```bash
   git checkout v0.6.x
   git merge main
   git push
   git checkout v0.5.x
   git merge main
   git push
   ```

## Release bot (`RELEASE_TOKEN`)

User-owned repositories cannot add the built-in GitHub Actions app as a ruleset bypass actor. The Release workflow therefore authenticates with a **fine-grained Personal Access Token** owned by a repo **Administrator** (the `Protect main` ruleset already bypasses `RepositoryRole` Administrator).

### One-time setup

1. GitHub → Settings → Developer settings → [Fine-grained personal access tokens](https://github.com/settings/personal-access-tokens) → **Generate new token**.
2. Configure:
   - **Resource owner:** `digitalelvis` (repo owner account)
   - **Repository access:** Only select `digitalelvis/neuronai-studio`
   - **Permissions → Repository:**
     - **Contents:** Read and write (push commit + tags)
     - **Metadata:** Read (required)
   - **Expiration:** 90 days (or shorter); set a calendar reminder to rotate
3. Copy the token once.
4. Repo → **Settings → Secrets and variables → Actions → New repository secret**:
   - Name: `RELEASE_TOKEN`
   - Value: the PAT
5. Confirm the ruleset **Protect main** still lists **Bypass → Repository role: Admin** (applied via `.github/scripts/apply-branch-rules.sh`).

Without `RELEASE_TOKEN`, the Release workflow fails **before** `release-it` runs (no orphan Packagist tags).

### Rotate

Generate a new fine-grained PAT → update the `RELEASE_TOKEN` secret → revoke the old token.

## Dry run locally

Preview the next version and changelog without publishing:

```bash
npm install
npm run release:dry
```

## Initial bootstrap (one-time setup)

Complete these steps when setting up release automation for a new repository:

### 1. Push `main` to GitHub

If `main` exists only locally:

```bash
git checkout main
git push -u origin main
```

### 2. Branch protection (rulesets)

Apply from the repo root (requires `gh` admin access):

```bash
./.github/scripts/apply-branch-rules.sh
```

That installs **Protect main** and **Protect development lines** from `.github/rulesets/`, including Administrator bypass for release pushes. Development lines match `refs/heads/v*.*.x` (covers `v0.5.x`, `v0.6.x`, …). Then create the [`RELEASE_TOKEN`](#release-bot-release_token) secret.

### 3. Register on Packagist

1. Create or sign in at [packagist.org](https://packagist.org).
2. Profile → **Link GitHub Account** (OAuth). Use an account with admin access to the `digitalelvis` org.
3. [Submit Package](https://packagist.org/packages/submit) → URL: `https://github.com/digitalelvis/neuronai-studio`.
4. Confirm the package name reads **`digitalelvis/neuronai-studio`** (from `composer.json`).
5. Enable **Auto-update** on the package settings page (installs the GitHub webhook).
6. After submit, confirm version **`v0.1.1`** appears (first release with the new vendor/namespace).

Validate install in a fresh Laravel app:

```bash
composer require digitalelvis/neuronai-studio neuron-core/neuron-laravel
php artisan neuron:install
php artisan neuronai-studio:install
```

### 3.3 GitHub Hook (auto-update)

If Packagist shows *"This package is not auto-updated"*, configure the webhook once.

**Option A — via Packagist (recommended)**

1. Open [digitalelvis/neuronai-studio on Packagist](https://packagist.org/packages/digitalelvis/neuronai-studio).
2. Click **Setup GitHub Hook** (or **Enable auto-update**) on the package page.
3. Authorize Packagist on GitHub when prompted (repo `digitalelvis/neuronai-studio`).
4. Refresh Packagist — the warning should disappear.

**Option B — manual webhook on GitHub**

1. Packagist → **Profile** → copy your **API Token**.
2. GitHub → `digitalelvis/neuronai-studio` → **Settings** → **Webhooks** → **Add webhook** (or edit existing):
   - **Payload URL:** `https://packagist.org/api/github?username=digitalelvis`
   - **Content type:** `application/json`
   - **Secret:** your Packagist API token
   - **Events:** Just the **push** event
3. Save, then use **Recent Deliveries** → **Redeliver** on a push event to confirm **200** response.

**Verify**

- Packagist package page no longer shows the auto-update warning.
- After a new tag on `main`, the version appears on Packagist within ~1 minute (or click **Update** on the package page to force sync).

### 4. First release

Tag **`v0.1.1`** is published with the `digitalelvis/neuronai-studio` vendor. Submit to Packagist (step 3) to make it installable via Composer.

For future releases:

1. Merge the active feature line (e.g. `v0.7.x`) → `main` via PR.
2. Release workflow creates the next semver tag (or run `npm run release:dry` locally to preview).
3. Verify tag and GitHub Release appear on the repository.
4. Verify Packagist shows the new version (auto-update webhook).
5. Back-merge `main` → active `vX.Y.x` lines.

## v0.8.x / v0.7.x / v0.6.x development lines

| Line | Role |
|------|------|
| **`v0.8.x`** | Active **feature** + **patch** line after `v0.8.0` (M7 shipped; next milestone TBD) |
| **`v0.7.x`** | **Patch** line for the published `0.7` series (M6) |
| **`v0.6.x`** | **Patch** line for the published `0.6` series |
| Latest published | **`v0.8.0`** (M7 external observability) |

| Area | Status |
|------|--------|
| M1–M4 (cyclic graphs, RAG, structured output, HITL, parallel, queue, stream adapters, unified runs) | ✅ Published in `v0.3.0` |
| Release bot (`RELEASE_TOKEN` + push `main` before tag) | ✅ Verified through `v0.8.0` |
| M5 `cost-estimation` | ✅ Shipped in `v0.4.0` |
| M5 `usage-analytics` | ✅ Shipped in `v0.5.0` |
| M5 `usage-export-api` | ✅ Shipped in `v0.6.0` |
| M6 runtime/agent | ✅ Shipped in `v0.7.0` |
| M7 external observability | ✅ Shipped in `v0.8.0` |

Lines `v0.3.x`–`v0.7.x` are closed for new features. Consumers on older minors can stay until ready to adopt `v0.8.0`+.

## Troubleshooting

| Issue | Cause | Fix |
|-------|-------|-----|
| Release workflow loops | Missing `[skip ci]` on release commit | Ensure `.release-it.json` has `[skip ci]` in `commitMessage` |
| `Missing secret RELEASE_TOKEN` | Secret not configured | Follow [Release bot](#release-bot-release_token) |
| Release push rejected on `main` (GH013) | Push used `GITHUB_TOKEN` or a non-admin PAT | Use Administrator `RELEASE_TOKEN`; confirm Admin bypass on **Protect main** |
| Orphan Packagist tag (tag exists, commit not on `main`) | Tag pushed before `main` (legacy workflow) or push to `main` rejected | Absorb tag via hotfix merge; current workflow pushes `main` **before** the tag |
| Release rolls back after tag | `release-it` GitHub plugin fails in CI (`Cannot read properties of null`) | Keep `"github.release": false` in `.release-it.json`; workflow creates the GitHub Release via `gh release create` |
| Release skipped after merge | Prior run failed before bump, or only `docs`/`chore` commits | Re-run **Release** via Actions → **workflow_dispatch** when a bump is expected |
| No version bump | Only `docs`/`chore` commits since last tag | Expected — `requireCommitsFail: false` skips release |
| Packagist stale | Hook not configured | Follow [3.3 GitHub Hook](#33-github-hook-auto-update); add API token as webhook secret |
| Packagist hook 403 | Webhook missing secret | Set **Secret** to your Packagist API token (Profile) |
| Wrong semver | Non-conventional commit messages | Rewrite is not possible after merge; use correct types going forward |
