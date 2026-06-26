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

Run NeuronAI evaluators discovered in a directory. Delegates to NeuronAI's `EvaluatorDiscovery` and `EvaluatorRunner`.

```bash
php artisan neuronai-studio:evaluations
php artisan neuronai-studio:evaluations --path=evaluators
php artisan neuronai-studio:evaluations --path=evaluators --verbose
```

| Option | Description |
|--------|-------------|
| `--path` | Directory containing evaluator classes (default: `evaluators`) |
| `--verbose` | Show evaluator names during execution |

Output drivers are configured in `evaluation.php` at the project root. Publish the stub:

```bash
php artisan vendor:publish --tag=neuronai-studio-evaluation
```

See [Evaluations](../guides/agents/evaluations.md).

## neuronai-studio:eval

Run an eval suite stored in the database against its linked agent.

```bash
php artisan neuronai-studio:eval {suite}
php artisan neuronai-studio:eval support-basic
php artisan neuronai-studio:eval 1 --fake
```

| Argument / Option | Description |
|-------------------|-------------|
| `{suite}` | Eval suite ID or slug |
| `--fake` | Use `FakeAIProvider` for deterministic runs (CI) |

Exit code is non-zero when any case fails. Results are persisted to `eval_runs` and `eval_run_items`.

See [Evaluations](../guides/agents/evaluations.md).

## Related Neuron Laravel commands

NeuronAI Studio depends on Neuron Laravel:

```bash
php artisan neuron:install
```

Configure LLM provider credentials through Neuron Laravel's config.
