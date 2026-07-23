# Installation

This guide walks through installing NeuronAI Studio in a Laravel application, from Composer dependencies to opening the dashboard.

## Prerequisites

- PHP 8.2 or higher
- Laravel 11, 12, or 13
- A database configured in your `.env`
- API credentials for at least one LLM provider (configured via Neuron AI)

## Step 1 — Install packages

```bash
composer require digitalelvis/neuronai-studio neuron-core/neuron-ai
```

NeuronAI Studio depends on [neuron-core/neuron-ai](https://docs.neuron-ai.dev/overview/getting-started). Provider credentials live in `config/neuron.php` (published by the installer) — the studio does not duplicate API key configuration.

## Step 2 — Install NeuronAI Studio

```bash
php artisan neuronai-studio:install
```

This publishes `config/neuron.php` and `config/neuronai-studio.php`, migrations, and assets. Set provider credentials in `.env` (for example `OPENAI_KEY`).

The install command also performs the following:

| Step | Publish tag | Description |
|------|-------------|-------------|
| Provider config | `neuron-config` | Publishes `config/neuron.php` (API keys / providers) |
| Config | `neuronai-studio-config` | Publishes `config/neuronai-studio.php` |
| Migrations | `neuronai-studio-migrations` | Copies migration files (also auto-loaded from package) |
| Assets | `neuronai-studio-assets` | Publishes pre-built JS/CSS to `public/vendor/neuronai-studio/` |
| Views | `neuronai-studio-views` | Only with `--with-views` flag |

The installer prompts to run migrations. Confirm unless you prefer to migrate manually.

### Install options

```bash
# Overwrite existing published config/migrations
php artisan neuronai-studio:install --force

# Publish Blade views for customization
php artisan neuronai-studio:install --with-views
```

## Step 3 — Publish assets (if needed)

The install command publishes assets automatically. Re-run manually after package updates or when rebuilding frontend bundles:

```bash
php artisan vendor:publish --tag=neuronai-studio-assets --force
```

## Step 4 — Open the dashboard

Visit `/{route_prefix}` — default:

```
http://localhost:8000/neuronai-studio
```

Configure the prefix with `NEURONAI_STUDIO_ROUTE_PREFIX` in `.env`.

## Optional — External observability

Studio Debugger traces are on by default. To also export to Inspector or Langfuse:

```bash
php artisan neuronai-studio:install-observability inspector
php artisan neuronai-studio:install-observability langfuse
```

See [Native tracing](../guides/observability/native-tracing.md), [Inspector](../guides/observability/inspector.md), and [Langfuse](../guides/observability/langfuse.md).

<!-- SCREENSHOT: install-success-dashboard -->
> **Screenshot pending:** Dashboard after successful installation.
>
> Asset path: `docs/assets/screenshots/install-success-dashboard.png`
> Capture: `/neuronai-studio` immediately after install — dark theme, 1440×900

![Dashboard after install](../assets/screenshots/install-success-dashboard.png)

## Environment variables

| Variable | Default | Description |
|----------|---------|-------------|
| `NEURONAI_STUDIO_ROUTE_PREFIX` | `neuronai-studio` | URL prefix for all studio routes |
| `NEURONAI_STUDIO_TABLE_PREFIX` | `neuronai_studio_` | Database table prefix |
| `NEURONAI_STUDIO_DEFAULT_PROVIDER` | `openai` | Default provider in agent forms |
| `NEURONAI_STUDIO_DEFAULT_MODEL` | `gpt-4o-mini` | Default model in agent forms |
| `NEURONAI_STUDIO_EXPORT_NAMESPACE` | `App\Neuron` | Namespace for exported PHP classes |
| `NEURONAI_STUDIO_EXPORT_PATH` | `app/Neuron` | Directory for exported files |
| `NEURONAI_STUDIO_ASYNC_RUNS_ENABLED` | `false` | Run workflows in background via queue jobs |
| `NEURONAI_STUDIO_QUEUE` | `default` | Queue name for workflow jobs |
| `NEURONAI_STUDIO_QUEUE_CONNECTION` | — | Optional queue connection override |
| `NEURONAI_STUDIO_QUEUE_TRIES` | `1` | Max job attempts |
| `NEURONAI_STUDIO_QUEUE_BACKOFF` | `30` | Retry delay in seconds |
| `NEURONAI_STUDIO_USAGE_EXPORT_ENABLED` | `true` | Host metering API (`GET …/usage`) |
| `NEURONAI_STUDIO_USAGE_EVENTS_ENABLED` | `false` | Dispatch `RunUsageRecorded` on terminal runs |

See [Configuration](../reference/configuration.md) for the full list. Auth for the usage export API is host-owned — set `usage.export.middleware` (e.g. `auth:sanctum`). See [Usage Export API](../guides/analytics/export-api.md).

### Async workflow runs (optional)

To execute workflows outside the synchronous test harness SSE path, enable async runs and start a queue worker:

```env
NEURONAI_STUDIO_ASYNC_RUNS_ENABLED=true
NEURONAI_STUDIO_QUEUE=default
```

```bash
php artisan queue:work --queue=default
```

Poll trace status at `GET /neuronai-studio/traces/{id}/json`. Details: [Runtime & Traces](../guides/workflows/runtime-and-traces.md#queue-runner).

## Publish tags reference

| Tag | Destination |
|-----|-------------|
| `neuron-config` | `config/neuron.php` |
| `neuronai-studio-config` | `config/neuronai-studio.php` |
| `neuronai-studio-migrations` | `database/migrations/` |
| `neuronai-studio-views` | `resources/views/vendor/neuronai-studio/` |
| `neuronai-studio-assets` | `public/vendor/neuronai-studio/` |

Details: [Publish Tags](../reference/publish-tags.md).

## Authorization

In the `local` environment, the studio gate allows all access. In other environments, authenticated users are required by default.

Define a custom gate in `AppServiceProvider`:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewNeuronAIStudio', function ($user) {
    return $user->isAdmin();
});
```

See [Security & Access](../guides/security-and-access.md).

## Troubleshooting

### UI looks outdated after a package update

If you previously published Blade views, your app may load stale templates instead of the package defaults:

```bash
rm -rf resources/views/vendor/neuronai-studio
php artisan view:clear
php artisan vendor:publish --tag=neuronai-studio-assets --force
```

### Rebuilding frontend assets (package development)

When editing `resources/js/` in the package source:

```bash
npm install
npm run build
php artisan vendor:publish --tag=neuronai-studio-assets --force
```

See [Frontend Bundles](../reference/frontend-bundles.md).

### Migrations fail

Ensure your database connection is configured and run:

```bash
php artisan migrate
```

Migrations are also loaded automatically from the package — you do not need to publish them unless you want to modify them.

## Next steps

- [Quickstart: First Agent](quickstart-first-agent.md) — create and test an agent in five minutes
- [Quickstart: First Workflow](quickstart-first-workflow.md) — run a workflow from a template
- [Demo App](demo-app.md) — local development with the bundled example app
