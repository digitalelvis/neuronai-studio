# Publish Tags

Laravel publish tags for vendor assets, config, and migrations.

## Tags

| Tag | Destination | When to use |
|-----|-------------|-------------|
| `neuron-config` | `config/neuron.php` | Provider credentials (API keys, embeddings, stores) |
| `neuronai-studio-config` | `config/neuronai-studio.php` | Customize studio configuration |
| `neuronai-studio-migrations` | `database/migrations/` | Modify migrations (optional — auto-loaded from package) |
| `neuronai-studio-views` | `resources/views/vendor/neuronai-studio/` | Override Blade templates |
| `neuronai-studio-assets` | `public/vendor/neuronai-studio/` | Publish JS/CSS bundles |
| `neuronai-studio-evaluation` | `evaluation.php` | Evaluation config stub |
| `neuronai-studio-evaluator` | `app/Evaluators/ExampleAgentEvaluator.php` | Example evaluator stub |

## Commands

```bash
# Provider credentials
php artisan vendor:publish --tag=neuron-config

# Config
php artisan vendor:publish --tag=neuronai-studio-config

# Migrations (optional)
php artisan vendor:publish --tag=neuronai-studio-migrations

# Views (only when customizing)
php artisan vendor:publish --tag=neuronai-studio-views

# Assets (required for UI)
php artisan vendor:publish --tag=neuronai-studio-assets --force
```

## Auto-loaded without publish

These load from the package automatically:

- Migrations via `loadMigrationsFrom()`
- Routes via `loadRoutesFrom()`
- Default views via `loadViewsFrom()`

## Asset rebuild workflow

After editing frontend source in the package:

```bash
npm install
npm run build
php artisan vendor:publish --tag=neuronai-studio-assets --force
```

## View override warning

If you published views and the UI looks outdated after a package update:

```bash
rm -rf resources/views/vendor/neuronai-studio
php artisan view:clear
```

Blade views load from the package by default — only publish views when you need to customize templates.

## See also

- [Installation](../getting-started/installation.md)
- [Frontend Bundles](frontend-bundles.md)
