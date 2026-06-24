# Studio Test Harness — Design

## Component tree

```
studio-chat/
├── main.jsx                 # mountStudioChat()
├── StudioTestHarness.jsx    # Playground + Chat shell
├── StudioChat.jsx
├── StudioPlayground.jsx
├── MessageList.jsx
├── Composer.jsx
├── adapters/
│   ├── AgentSessionAdapter.js
│   └── WorkflowSessionAdapter.js
├── utils/
│   ├── fetchSse.js
│   └── presets.js
└── chat.css
```

## SessionAdapter interface

```javascript
class SessionAdapter {
  async *send(message, attachments, context) {}
  async *resume(message) {}
  reset() {}
}
```

## API endpoints

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/agents/{agent}/chat/stream` | Agent chat SSE |
| POST | `/workflows/{workflow}/run/stream` | Workflow run SSE |
| POST | `/workflows/runs/{run}/resume/stream` | Resume after Human node |
| POST | `/studio/attachments` | Upload attachment |

## Workflow resume

`HumanNodeExecutor` throws `HumanInputRequiredException`. `WorkflowRunner` saves checkpoint, status `awaiting_input`. Resume sets human output, continues via `GraphExecutionLoop.runFromNode()`.
