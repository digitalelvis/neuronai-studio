# NeuronAI Studio Demo App

This directory contains a demo Laravel application for developing and testing the package.

## Setup

From the repository root:

```bash
# Create a fresh Laravel app (if not already present)
composer create-project laravel/laravel examples/demo-app-tmp "^12.0"
cp examples/demo-app/composer.json examples/demo-app-tmp/composer.json

cd examples/demo-app-tmp
composer update
cp .env.example .env
php artisan key:generate

# Configure Neuron AI credentials in .env
# OPENAI_KEY=sk-...
# NEURON_AI_PROVIDER=openai

php artisan migrate
php artisan vendor:publish --tag=neuronai-studio-assets --force
php artisan serve
```

Visit `http://localhost:8000/neuronai-studio`.

## Alternative: path repository

Add to your Laravel app's `composer.json`:

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

Then run:

```bash
composer require digitalelvis/neuronai-studio:@dev neuron-core/neuron-ai
php artisan neuronai-studio:install
php artisan vendor:publish --tag=neuronai-studio-assets
```
