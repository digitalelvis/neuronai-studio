# Usage Export API

Host-facing REST endpoints for metering tokens and estimated cost. Independent of Studio UI and of stream adapters — enable export even when integrate stream protocols are off.

## Enable

```env
NEURONAI_STUDIO_USAGE_EXPORT_ENABLED=true
# Optional overrides (null falls back to stream_adapters.*):
# NEURONAI_STUDIO_USAGE_EXPORT_PREFIX=api/neuronai
```

Default prefix and middleware come from `stream_adapters.route_prefix` / `stream_adapters.middleware`. Auth is **host-owned** — put `auth:sanctum` (or your gate) in middleware:

```php
'usage' => [
    'export' => [
        'enabled' => true,
        'route_prefix' => null, // → stream_adapters.route_prefix
        'middleware' => ['api', 'auth:sanctum'],
    ],
],
```

Disable with `NEURONAI_STUDIO_USAGE_EXPORT_ENABLED=false` (routes are not registered).

## Aggregate

`GET /{prefix}/usage?from=2026-07-01&to=2026-07-15`

Optional query params:

| Param | Description |
|-------|-------------|
| `entity_type` | `agent` or `workflow` |
| `entity_id` | Entity primary key |
| `group_by` | `model` or `entity` |
| `model` | Filter to llm spans with this model string |

Child runs (`parent_run_id` set) are **excluded** from window totals (usage is rolled into the parent). Empty windows return HTTP 200 with zero totals.

```json
{
  "currency": "USD",
  "from": "2026-07-01T00:00:00+00:00",
  "to": "2026-07-15T23:59:59+00:00",
  "totals": {
    "prompt_tokens": 1200,
    "completion_tokens": 400,
    "total_tokens": 1600,
    "estimated_cost": "0.012500",
    "run_count": 12
  },
  "breakdown": []
}
```

With `group_by=model`, `breakdown` entries include `provider`, `model`, tokens, and `estimated_cost`. Model breakdown is computed from llm spans by span `started_at` (includes nested agent spans under workflows).

## Per-run detail

`GET /{prefix}/usage/runs/{run}`

Returns tokens, estimated cost, entity reference, status, timestamps, and compact llm `spans`. Missing run → 404. Parent workflow totals include nested children; `spans` lists **own** llm spans only.

## Events (optional)

```env
NEURONAI_STUDIO_USAGE_EVENTS_ENABLED=true
```

When enabled, terminal runs dispatch `DigitalElvis\NeuronAIStudio\Events\RunUsageRecorded` with tokens, cost, currency, entity, and `parentRunId`. Independent of the HTTP export flag.

## Related

- [Cost estimation](costs.md)
- [Usage analytics (Studio UI)](usage.md)
- [Configuration](../../reference/configuration.md)
