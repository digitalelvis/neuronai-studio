# Cost Estimation

NeuronAI Studio estimates spend per LLM inference from token usage and a host-editable pricing table. Estimates power Debugger run totals and (when enabled) usage export / analytics — they are **not** provider invoices.

## How it works

1. On each LLM completion (`inference-stop`, or a direct LLM-node provider call), Studio records an `llm` span with `provider`, `model`, token counts, and `estimated_cost`.
2. Tokens and cost are rolled into the owning `runs` row.
3. Nested agent work under a workflow sets `parent_run_id` on the child run and rolls usage up to the parent. On terminal status, totals are recomputed as **own spans + child runs**.

Formula (currency from config, default USD):

```
estimated_cost =
  (prompt_tokens / 1000) * prompt_per_1k
  + (completion_tokens / 1000) * completion_per_1k
```

Missing pricing keys, null usage, or malformed rates yield cost `0` — the run never fails because of pricing.

## Configure pricing

Publish the config if you have not already:

```bash
php artisan vendor:publish --tag=neuronai-studio-config
```

Edit `config/neuronai-studio.php`:

```php
'usage' => [
    'currency' => env('NEURONAI_STUDIO_USAGE_CURRENCY', 'USD'),

    'pricing' => [
        'openai' => [
            'gpt-4o-mini' => [
                'prompt_per_1k' => 0.00015,
                'completion_per_1k' => 0.0006,
            ],
            // Add aliases that match the exact model string on the agent / LLM node.
        ],
    ],
],
```

Or via env for currency only:

```env
NEURONAI_STUDIO_USAGE_CURRENCY=USD
```

Rates are **per 1k tokens** in the install currency. The package ships approximate public list prices for catalog models; **override them** with your negotiated rates before relying on numbers for budgeting.

### Matching keys

Pricing lookup is an exact match on `provider` + `model` strings stored on the span. If the agent uses `gpt-4o-mini-2024-07-18` but config only has `gpt-4o-mini`, cost stays `0` until you add that key (or point the agent at a keyed model).

Ollama defaults are `0` (local).

## What gets metered

| Path | Metered? |
|------|----------|
| Agent playground / integrate stream | Yes (`stream` / `streamHandler`) |
| Agent inline chat / stream / structured / resume | Yes |
| Workflow **Agent** node | Yes (child run + parent rollup) |
| Workflow **LLM** node (chat / stream / structured) | Yes (span on workflow run) |
| Embeddings / RAG indexing | No (out of scope) |

## Caveats

- Estimates ≠ invoices. Providers may bill cached tokens, tool calls, or image units differently.
- One currency per install (`usage.currency`).
- Parent workflow totals include nested child runs after finalize — do not sum parent + children again in external billing aggregations without excluding `parent_run_id IS NOT NULL`.

## See also

- [Configuration](../../reference/configuration.md#usage--cost-estimation)
- [Database schema](../../reference/database-schema.md) — `runs`, `traces`, `trace_spans`
