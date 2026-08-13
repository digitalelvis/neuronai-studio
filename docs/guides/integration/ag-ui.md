# AG-UI Integration Guide

NeuronAI Studio streams Agent and Workflow runs in the [AG-UI](https://docs.ag-ui.com/concepts/events) event protocol (`RUN_STARTED`, `TEXT_MESSAGE_*`, `TOOL_CALL_*`, `MESSAGES_SNAPSHOT`, `STATE_SNAPSHOT` / `STATE_DELTA`, `RUN_FINISHED`).

CopilotKit v2 `HttpAgent` can POST **RunAgentInput** directly to these routes. The legacy Studio body `{ message, thread_id }` still works.

## Streaming Endpoints

- **Agent Stream:** `POST {prefix}/agents/{agent}/stream/agui`
- **Workflow Stream:** `POST {prefix}/workflows/{workflow}/stream/agui`
- **Workflow Resume (legacy):** `POST {prefix}/workflows/traces/{trace}/resume/agui`

`{prefix}` defaults to `api/neuronai`.

## RunAgentInput (CopilotKit / HttpAgent)

```ts
import { HttpAgent } from '@ag-ui/client';

const agent = new HttpAgent({
  url: 'https://your-domain.com/api/neuronai/agents/1/stream/agui',
});

await agent.runAgent({
  threadId: 't-123',
  runId: 'r-1',
  messages: [{ id: 'm1', role: 'user', content: 'Hello' }],
  tools: [], // ignored — Studio streams server tools as TOOL_CALL_*
  state: {},
  context: [],
});
```

Equivalent fetch:

```ts
await fetch('https://your-domain.com/api/neuronai/workflows/1/stream/agui', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    threadId: 't-123',
    runId: 'r-1',
    messages: [{ id: 'm1', role: 'user', content: 'Start request' }],
    tools: [],
    state: {},
    context: [],
  }),
});
```

Studio maps the **last `role: user` message** to the runner input. `RUN_STARTED` / `RUN_FINISHED` echo the client `threadId` and `runId`. `threadId` is a string (not required to be a UUID).

### Fallback body

Hosts that are not CopilotKit may still send:

```json
{ "message": "Start request", "thread_id": "…" }
```

## Snapshots

After `RUN_STARTED` the stream emits:

- `MESSAGES_SNAPSHOT` — Studio thread history (`id`, `role`, `content`) so the client can hydrate without a host preload.
- `STATE_SNAPSHOT` — workflow shared state with reserved `__*` keys stripped. Agents emit `{}`.

Workflows also emit `STATE_DELTA` (RFC 6902 JSON Patch) when a step changes client-facing state.

## Resume flow (Human / HITL)

When a workflow pauses (`awaiting_input` or `awaiting_tool_approval`) the AG-UI stream **dual-emits**:

1. `CUSTOM` `name: awaiting_input` with `trace_id` (M4 clients).
2. `RUN_FINISHED` with `outcome: { type: "interrupt", interrupts: [{ interruptId, reason, payload }] }`.
   `interruptId` is the Studio run UUID (same as `trace_id`).

### Canonical resume (same stream URL)

```ts
await fetch('https://your-domain.com/api/neuronai/workflows/1/stream/agui', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    threadId: 't-123',
    runId: 'r-2',
    resume: [{
      interruptId: '<run uuid from interrupt>',
      status: 'resolved',
      payload: { message: 'User response' },
    }],
  }),
});
```

Tool approval: `payload.approval` = `approve` | `reject`. `status: cancelled` is not supported (422).

### Legacy resume URL

```ts
await fetch(`https://your-domain.com/api/neuronai/workflows/traces/${traceId}/resume/agui`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    message: 'User response',
  }),
});
```

## Playground

The Studio playground uses the **internal** SSE protocol (`token`, `tool_*`), not AG-UI. See [Stream Adapters](stream-adapters.md).
