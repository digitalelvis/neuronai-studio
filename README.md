# NeuronAI Studio

Visual AI Agent Builder for Laravel powered by [Neuron AI](https://neuron-ai.dev).

Create agents, design workflow graphs, run them at runtime for prototyping, and export production-ready PHP classes.

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- [neuron-core/neuron-laravel](https://github.com/neuron-core/neuron-laravel) ^1.0

## Installation

```bash
composer require elvislopesdigital/neuronai-studio neuron-core/neuron-laravel
php artisan neuron:install
php artisan neuronai-studio:install
```

Publish assets for the dashboard UI:

```bash
php artisan vendor:publish --tag=neuronai-studio-assets
```

Blade views load from the package automatically. Only publish views if you need to customize templates:

```bash
php artisan vendor:publish --tag=neuronai-studio-views
```

If you previously published views and the UI looks outdated after a package update, remove the override directory and clear compiled views:

```bash
rm -rf resources/views/vendor/neuronai-studio
php artisan view:clear
```

Build frontend assets (from the package root when developing):

```bash
npm install
npm run build
```

`npm run build` produces four bundles: `studio-ui.css`, `workflow-canvas.bundle.js`, `studio-chat.bundle.js`, and `studio-forms.bundle.js`.

## Usage

Open the dashboard at `/neuronai-studio` (configurable via `NEURONAI_STUDIO_ROUTE_PREFIX`).

### Features

- **Agents** — CRUD with provider, model, and system prompt
- **Playground** — Test agents in a chat UI
- **Workflows** — Visual graph editor with drag-and-drop nodes ([workflow state & condition node](docs/workflow-state.md))
- **Runtime execution** — Run workflows from the UI with step-by-step history
- **PHP export** — Generate Neuron Agent/Workflow classes for production

### Export to PHP

```bash
php artisan neuronai-studio:export agent 1
php artisan neuronai-studio:export workflow 1
```

Or use the **Export PHP** button in the workflow editor.

## Configuration

Publish config:

```bash
php artisan vendor:publish --tag=neuronai-studio-config
```

Key options in `config/neuronai-studio.php`:

- `route_prefix` — Dashboard URL prefix (default: `neuronai-studio`)
- `export_namespace` — PHP namespace for exported classes (default: `App\Neuron`)
- `export_path` — Directory for exported files (default: `app/Neuron`)
- `providers` — AI providers available in the UI

Credentials are read from `config/neuron.php` — no duplicate API key configuration.

## Demo App

See [examples/demo-app](examples/demo-app) for a Laravel 12 demo installation.

## License

MIT
