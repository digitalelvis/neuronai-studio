# Demo App

The repository includes an example Laravel application for local development and testing.

## Location

```
examples/demo-app/
```

See the [demo app README](https://github.com/digitalelvis/neuronai-studio/blob/main/examples/demo-app/README.md) in the repository.

## Path repository setup

Add a path repository to your Laravel app's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../neuronai-studio"
        }
    ],
    "require": {
        "digitalelvis/neuronai-studio": "@dev",
        "neuron-core/neuron-ai": "^3.15"
    }
}
```

Then install:

```bash
composer require digitalelvis/neuronai-studio:@dev neuron-core/neuron-ai
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
