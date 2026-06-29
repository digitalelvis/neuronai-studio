# Demo App

The repository includes an example Laravel application for local development and testing.

## Location

```
examples/demo-app/
```

See the [demo app README](https://github.com/elvislopesdigital/neuronai-studio/blob/main/examples/demo-app/README.md) in the repository.

## Path repository setup

Add a path repository to your Laravel app's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../laravel-agent-builder"
        }
    ],
    "require": {
        "elvislopesdigital/neuronai-studio": "@dev",
        "neuron-core/neuron-laravel": "^1.0"
    }
}
```

Then install:

```bash
composer require elvislopesdigital/neuronai-studio:@dev neuron-core/neuron-laravel
php artisan neuron:install
php artisan neuronai-studio:install
php artisan vendor:publish --tag=neuronai-studio-assets --force
php artisan serve
```

Visit `http://localhost:8000/neuronai-studio`.

## Configure credentials

Add provider keys to `.env`:

```env
OPENAI_KEY=sk-...
NEURON_AI_PROVIDER=openai
```

## Developing the package UI

When editing React bundles in `resources/js/`:

```bash
# From package root
npm install
npm run build
php artisan vendor:publish --tag=neuronai-studio-assets --force
```

Refresh the browser. See [Frontend Bundles](../reference/frontend-bundles.md) and [Contributing to Studio UI](../extending/contributing-to-studio-ui.md).

## Running tests

From the package root:

```bash
composer test
```

See [CONTRIBUTING.md](../../CONTRIBUTING.md) for the full development workflow.
