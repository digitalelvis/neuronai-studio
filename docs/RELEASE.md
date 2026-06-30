# Release Process

Operational guide for versioning and publishing `digitalelvis/neuronai-studio` on Packagist.

## Overview

| Component | Role |
|-----------|------|
| `release-it` | Semver bump, `CHANGELOG.md`, Git tag, GitHub Release |
| `.github/workflows/release.yml` | Runs `release-it --ci` on every push to `main` (skips `[skip ci]` commits) |
| `.github/workflows/ci.yml` | PHPUnit + frontend build gate on PRs to `main` and `v*.*.x` |
| Packagist | Consumes Git tags (`v1.2.3`) — no `version` field in `composer.json` |

## Day-to-day development

1. Branch from the active development line (currently `v0.0.x`):

   ```bash
   git checkout v0.0.x
   git pull
   git checkout -b feat/my-feature
   ```

2. Commit using [Conventional Commits](https://conventionalcommits.org) (`feat(studio):`, `fix(canvas):`, etc.).

3. Open a PR targeting `v0.0.x`. CI must pass before merge.

## Standard release

1. Ensure `v0.0.x` is stable and CI is green.

2. Open a PR from `v0.0.x` → `main`. Title example: `release: v0.1.0`.

3. Merge the PR. The Release workflow will:
   - Analyze commits since the last tag
   - Bump `package.json` version (release-it anchor only — package is not published to npm)
   - Update `CHANGELOG.md`
   - Commit `chore(release): X.Y.Z [skip ci]`
   - Create tag `vX.Y.Z`
   - Publish a GitHub Release

4. Packagist picks up the new tag automatically (when auto-update is enabled).

5. Back-merge `main` into `v0.0.x` to sync the changelog:

   ```bash
   git checkout v0.0.x
   git pull
   git merge main
   git push
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
   git checkout v0.0.x
   git merge main
   git push
   ```

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

### 2. Branch protection (GitHub UI)

Repository → Settings → Branches → Add rule:

**`main`**

- Require a pull request before merging
- Require status checks to pass: `test` (from CI workflow)
- Do not allow bypassing (recommended)

**`v0.0.x`** (or current development line)

- Require a pull request before merging
- Require status checks to pass: `test`

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

1. Merge `v0.0.x` → `main` via PR.
2. Release workflow creates the next semver tag (or run `npm run release:dry` locally to preview).
3. Verify tag and GitHub Release appear on the repository.
4. Verify Packagist shows the new version (auto-update webhook).
5. Back-merge `main` → `v0.0.x`.

## Troubleshooting

| Issue | Cause | Fix |
|-------|-------|-----|
| Release workflow loops | Missing `[skip ci]` on release commit | Ensure `.release-it.json` has `[skip ci]` in `commitMessage` |
| No version bump | Only `docs`/`chore` commits since last tag | Expected — `requireCommitsFail: false` skips release |
| Packagist stale | Hook not configured | Follow [3.3 GitHub Hook](#33-github-hook-auto-update); add API token as webhook secret |
| Packagist hook 403 | Webhook missing secret | Set **Secret** to your Packagist API token (Profile) |
| Wrong semver | Non-conventional commit messages | Rewrite is not possible after merge; use correct types going forward |
