# Setup & installation

## Workflow

1. `composer require digitalelvis/neuronai-studio neuron-core/neuron-ai`
2. `php artisan neuronai-studio:install` (config, migrations, assets; optional `--with-views`, `--force`)
3. Set provider credentials in `.env` (e.g. `OPENAI_KEY`) via published `config/neuron.php`
4. Open `/{route_prefix}` (default `/neuronai-studio`)
5. Optional: observability (`neuronai-studio:install-observability inspector|langfuse`), async queue runs

## Checklist

- [ ] `neuron.php` + `neuronai-studio.php` published
- [ ] Migrations run
- [ ] Assets at `public/vendor/neuronai-studio/`
- [ ] Gate `viewNeuronAIStudio` if non-local
- [ ] After package update: republish assets `--force`; remove stale published views if UI looks old

## Key env

| Variable | Role |
|----------|------|
| `NEURONAI_STUDIO_ROUTE_PREFIX` | URL prefix |
| `NEURONAI_STUDIO_TABLE_PREFIX` | DB table prefix |
| `NEURONAI_STUDIO_EXPORT_PATH` / `NAMESPACE` | Export target |
| `NEURONAI_STUDIO_ASYNC_RUNS_ENABLED` | Queue runner for long workflows |

## Documentation (canonical)

- [Installation](../../../docs/getting-started/installation.md)
- [Quickstart: First Agent](../../../docs/getting-started/quickstart-first-agent.md)
- [Quickstart: First Workflow](../../../docs/getting-started/quickstart-first-workflow.md)
- [Demo App](../../../docs/getting-started/demo-app.md)
- [Configuration](../../../docs/reference/configuration.md)
- [Artisan Commands](../../../docs/reference/artisan-commands.md)
- [Publish Tags](../../../docs/reference/publish-tags.md)
- [Security & Access](../../../docs/guides/security-and-access.md)
