# Native tracing (Debugger)

NeuronAI Studio persists local traces in the database for the Studio Debugger, usage metering, and analytics.

This is the **native** layer — complementary to external exporters ([Inspector](./inspector.md), [Langfuse](./langfuse.md)).

## Default

Native tracing is **on** by default.

```env
NEURONAI_STUDIO_NATIVE_TRACING=true
```

## Disable

When you only want external APM and want to avoid Studio span writes:

```env
NEURONAI_STUDIO_NATIVE_TRACING=false
```

With native tracing off:

- `TelemetryTracker` is not attached
- New `StudioTraceSpan` rows are not created from agent/workflow EventBus events
- Agent and workflow runs still complete successfully
- External observers (Inspector / Langfuse) can still attach when configured

## Where to look

Studio UI → Debugger / Trace detail for a run (when native tracing was on for that run).

See also [Runtime and traces](../workflows/runtime-and-traces.md).
