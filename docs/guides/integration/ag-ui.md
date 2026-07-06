# AG-UI Integration Guide

NeuronAI Studio supports streaming Agent and Workflow executions using the AG-UI protocol format (`RUN_STARTED`, `TEXT_MESSAGE_*`, `RUN_FINISHED`).

## Streaming Endpoints

- **Agent Stream:** `POST {prefix}/agents/{agent}/stream/agui`
- **Workflow Stream:** `POST {prefix}/workflows/{workflow}/stream/agui`
- **Workflow Resume:** `POST {prefix}/workflows/traces/{trace}/resume/agui`

## Example Usage

```ts
const response = await fetch('https://your-domain.com/api/neuronai/workflows/1/stream/agui', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    message: 'Start request',
  }),
});

const reader = response.body.getReader();
const decoder = new TextDecoder();

while (true) {
  const { done, value } = await reader.read();
  if (done) break;
  console.log(decoder.decode(value));
}
```

## Resume Flow for Human Node Interrupts

When an AG-UI workflow stream encounters a Human node interrupt, it emits a `CUSTOM` event with name `awaiting_input` containing the `trace_id`.

Call the resume endpoint to supply the input and continue the stream:

```ts
await fetch(`https://your-domain.com/api/neuronai/workflows/traces/${traceId}/resume/agui`, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    message: 'User response',
  }),
});
```
