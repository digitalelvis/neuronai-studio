# Contributing to NeuronAI Studio

Thank you for your interest in contributing! This project is open source and we welcome pull requests.

## Prerequisites

- PHP 8.2+
- Composer
- Node.js 18+ (for frontend development)
- Laravel 11 or 12 (via demo app)

## Local development

1. Clone the repository
2. Set up the demo app — see [docs/getting-started/demo-app.md](docs/getting-started/demo-app.md)
3. Configure LLM credentials in `.env`
4. Run tests:

```bash
composer test
```

## Branch naming

| Prefix | Use |
|--------|-----|
| `feature/*` | New features |
| `fix/*` | Bug fixes |
| `docs/*` | Documentation only |
| `chore/*` | Maintenance, CI, deps |

Target `main` for pull requests.

## Documentation contributions

Documentation lives in `docs/` and is published via GitBook Git Sync.

### Rules

- Write in **English**
- Update `docs/SUMMARY.md` when adding new pages
- Add Mermaid diagrams for non-trivial flows
- For UI documentation, add a screenshot placeholder:

```markdown
<!-- SCREENSHOT: my-feature -->
> **Screenshot pending:** Description of what to capture.
>
> Asset path: `docs/assets/screenshots/my-feature.png`

![My feature](assets/screenshots/my-feature.png)
```

Use `../assets/screenshots/` from `docs/guides/`, `../../assets/screenshots/` from `docs/guides/<section>/`, `../../../assets/screenshots/` from deeper nested dirs, or `assets/screenshots/` only from `docs/` root.

- Register the tag in `docs/assets/screenshots/PENDING.md`

### Validate locally

```bash
# Check SUMMARY.md links resolve (after CI script is added)
.github/scripts/validate-docs.sh
```

## Code contributions

- Follow existing code style and conventions
- Add tests for new behavior in `tests/`
- Run `composer test` before submitting
- For UI changes: `npm run build` and republish assets

## Pull request checklist

- [ ] Tests pass (`composer test`)
- [ ] Documentation updated (if behavior changed)
- [ ] `docs/SUMMARY.md` updated (if new doc pages)
- [ ] Screenshot tag added to `PENDING.md` (if UI docs)
- [ ] CHANGELOG.md updated under `[Unreleased]` (if user-facing change)

## GitBook publishing

Docs sync automatically to GitBook on merge to `main`. See [docs/getting-started/gitbook-setup.md](docs/getting-started/gitbook-setup.md).

## Questions?

Open a GitHub issue for bugs, feature requests, or questions.
