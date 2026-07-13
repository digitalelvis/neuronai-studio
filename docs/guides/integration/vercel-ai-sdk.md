# Vercel AI SDK Integration Guide

NeuronAI Studio supports streaming Agent and Workflow responses directly to React or Next.js applications using Vercel AI SDK's `useChat` hook.

## Agent Streaming

Point `useChat` to your agent stream endpoint:

```tsx
import { useChat } from 'ai/react';

export function AgentChat() {
  const { messages, input, handleInputChange, handleSubmit } = useChat({
    api: 'https://your-domain.com/api/neuronai/agents/1/stream/vercel',
  });

  return (
    <div className="chat-container">
      {messages.map((m) => (
        <div key={m.id} className={`message ${m.role}`}>
          {m.content}
        </div>
      ))}
      <form onSubmit={handleSubmit}>
        <input value={input} onChange={handleInputChange} placeholder="Ask agent..." />
        <button type="submit">Send</button>
      </form>
    </div>
  );
}
```

## Workflow Streaming

Workflows can also be consumed with `useChat`:

```tsx
import { useChat } from 'ai/react';

export function WorkflowRunner() {
  const { messages, input, handleInputChange, handleSubmit } = useChat({
    api: 'https://your-domain.com/api/neuronai/workflows/1/stream/vercel',
  });

  return (
    <form onSubmit={handleSubmit}>
      <input value={input} onChange={handleInputChange} />
      <button type="submit">Run Workflow</button>
    </form>
  );
}
```

## Resuming Workflows (Human Node)

When a workflow hits a Human node, the stream finishes carrying a terminal data payload containing `awaiting_input` and the `trace_id`. To resume execution:

```ts
await fetch(`https://your-domain.com/api/neuronai/workflows/traces/${traceId}/resume/vercel`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ message: 'User approved input' }),
});
```
