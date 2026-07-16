# Usage Analytics

NeuronAI Studio surfaces lightweight usage signals on existing screens. It does not add a separate analytics dashboard.

## Dashboard

The Dashboard shows total tokens and estimated cost for the last 30 days. Nested agent runs are excluded from the window query because their usage is already rolled into the parent workflow run. Recent runs also show tokens and estimated cost.

## Debugger

Trace lists show compact total-token values. Trace details show prompt, completion, and total tokens plus estimated cost. LLM spans include provider, model, tokens, and cost in the timeline and step detail.

## Test Pretty view

Completed workflow and agent messages show run-level tokens and estimated cost. Agent and LLM workflow steps show their own usage next to duration. Older trace payloads without usage fields continue to render without chips.

The currency comes from `neuronai-studio.usage.currency`. Costs are estimates based on the configured model rates, not provider invoices.

## Host metering

Use the [Usage Export API](export-api.md) for host-facing aggregates and per-run reconciliation. Studio surfaces above are for operators only.

## Related

- [Cost Estimation](costs.md)
- [Dashboard](../dashboard.md)
- [Runtime & Traces](../workflows/runtime-and-traces.md)

