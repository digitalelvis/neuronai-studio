# Artisan Commands

NeuronAI Studio registers the following Artisan commands.

## neuronai-studio:install

Install the package — publish config, migrations, and assets.

```bash
php artisan neuronai-studio:install
php artisan neuronai-studio:install --force
php artisan neuronai-studio:install --with-views
```

| Option | Description |
|--------|-------------|
| `--force` | Overwrite existing published config/migrations |
| `--with-views` | Publish Blade views for customization |

See [Installation](../getting-started/installation.md).

## neuronai-studio:export

Export studio definitions to PHP classes.

```bash
php artisan neuronai-studio:export agent {id}
php artisan neuronai-studio:export workflow {id}
```

Output path: `config('neuronai-studio.export_path')` (default `app/Neuron`).

Tools are exported from the studio UI (**Export PHP** button in the tool editor), not via CLI.

See [Export & Production](../guides/export-and-production.md).

## neuronai-studio:make-tool

Scaffold a Neuron Tool PHP class.

```bash
php artisan neuronai-studio:make-tool {name}
```

Example:

```bash
php artisan neuronai-studio:make-tool WeatherLookup
```

See [Make Tool CLI](../guides/tools/make-tool-cli.md).

## neuronai-studio:evaluations

Manage agent evaluations (when evaluation features are enabled).

```bash
php artisan neuronai-studio:evaluations
```

## neuronai-studio:eval-suite

Run evaluation suites against agents.

```bash
php artisan neuronai-studio:eval-suite
```

## Related Neuron Laravel commands

NeuronAI Studio depends on Neuron Laravel:

```bash
php artisan neuron:install
```

Configure LLM provider credentials through Neuron Laravel's config.
