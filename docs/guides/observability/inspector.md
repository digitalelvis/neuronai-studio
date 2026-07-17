# Inspector APM

Export Neuron agent and workflow events to [Inspector](https://inspector.dev) without replacing the Studio Debugger.

## Prerequisites

- Inspector account + ingestion key
- No extra Composer package (ships with Neuron / Inspector PHP)

## Setup (< 5 minutes)

1. Copy your ingestion key from Inspector.
2. Add to `.env`:

```env
INSPECTOR_INGESTION_KEY=your_ingestion_key
```

3. Run an agent or workflow in the Studio playground (or host integrate routes).
4. Open the Inspector dashboard — you should see inference, tools, and workflow segments.

Optional force-off (even if the key is set):

```env
NEURONAI_STUDIO_INSPECTOR_ENABLED=false
```

Env-first default: `NEURONAI_STUDIO_INSPECTOR_ENABLED` is `true`. Attachment happens only when the key is present.

## Why Studio attaches explicitly

Calling `$agent->observe(TelemetryTracker)` initializes the Neuron EventBus scope. Neuron then **does not** auto-register `InspectorObserver`. Studio’s `ObservabilityManager` always attaches `Inspector\Neuron\InspectorObserver::instance()` when Inspector is active — fixing the “key set but nothing in Inspector” gap.

## Checklist CLI

```bash
php artisan neuronai-studio:install-observability inspector
```

## Related

- [Native tracing](./native-tracing.md)
- [Configuration reference](../../reference/configuration.md#observability)
