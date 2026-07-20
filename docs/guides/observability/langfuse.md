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

**Do not set** `LANGFUSE_NEURON_AI_ENABLED=true`. The package’s built-in `NeuronAiObserver` is incompatible with Neuron AI 3.15+ (`onEvent` missing `?string $branchId`) and fatals on autoload. Studio attaches its own observer instead.

3. Run an agent or workflow.
4. Open the Langfuse dashboard and confirm traces/generations.

Optional force-off:

```env
NEURONAI_STUDIO_LANGFUSE_ENABLED=false
```

Env-first default: enabled is `true`; attachment requires both keys **and** the package. Missing package → warn once, run continues.

## Studio adapter

Studio attaches a `LangfuseNeuronObserverAdapter` that implements Neuron’s full `ObserverInterface` (including `?string $branchId`) and talks to the Langfuse **client** from `axyr/laravel-langfuse`. It does **not** load `Axyr\Langfuse\NeuronAi\NeuronAiObserver`.

**Sessions:** Studio maps `StudioThread` (`thread_id`) → Langfuse `sessionId`, so multiple runs in the same chat/playground thread appear under one session. Each `StudioRun` opens a new Langfuse trace. Optional `user_id` can be passed via attach meta.

Direct LLM node calls (outside the Agent loop) also attempt a best-effort Langfuse generation via the package facade.

## Checklist CLI

```bash
php artisan neuronai-studio:install-observability langfuse
```

## Related

- [Native tracing](./native-tracing.md)
- [Inspector](./inspector.md)
- [Configuration reference](../../reference/configuration.md#observability)
