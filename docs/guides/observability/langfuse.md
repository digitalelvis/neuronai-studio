# Langfuse

Export LLM traces to [Langfuse](https://langfuse.com) for cost, prompt, and eval tooling. Optional host dependency — Studio never requires the package.

## Prerequisites

- Langfuse Cloud or self-hosted project
- Public + secret API keys

## Setup (< 5 minutes)

1. Install in the **host** Laravel app:

```bash
composer require axyr/laravel-langfuse
```

2. Add to `.env`:

```env
LANGFUSE_PUBLIC_KEY=pk-lf-...
LANGFUSE_SECRET_KEY=sk-lf-...
LANGFUSE_BASE_URL=https://cloud.langfuse.com
```

`LANGFUSE_HOST` is accepted as an alias for `LANGFUSE_BASE_URL`.

3. Run an agent or workflow.
4. Open the Langfuse dashboard and confirm traces/generations.

Optional force-off:

```env
NEURONAI_STUDIO_LANGFUSE_ENABLED=false
```

Env-first default: enabled is `true`; attachment requires both keys **and** the package. Missing package → warn once, run continues.

## Studio adapter

Studio attaches a `LangfuseNeuronObserverAdapter` that implements Neuron’s full `ObserverInterface` (including `?string $branchId`) and forwards events to `Axyr\Langfuse\NeuronAi\NeuronAiObserver` when available.

Direct LLM node calls (outside the Agent loop) also attempt a best-effort Langfuse generation via the package facade.

## Checklist CLI

```bash
php artisan neuronai-studio:install-observability langfuse
```

## Related

- [Native tracing](./native-tracing.md)
- [Inspector](./inspector.md)
- [Configuration reference](../../reference/configuration.md#observability)
