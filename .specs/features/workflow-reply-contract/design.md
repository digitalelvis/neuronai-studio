# Workflow Reply Contract — Design notes

## Components

| Piece | Role |
|-------|------|
| `StopNodeExecutor` | Interpolates `data.reply` → `state.reply` |
| `WorkflowReplyResolver` | Single reader for channels |
| `WorkflowStreamBridge` | Human prompt as TextChunk; `publish_reply` gate on tokens; resolver fallback |
| `GraphValidator` | Duplicate handle **errors**; Stop/Human/condition/loop/switch **warnings** |

## Nested child output

- Child has `reply` key → parent/`WorkflowAsTool` stores reply text (unless `output_mode: state`)
- Child without `reply` → full state JSON (backward compatible)

## Stream isolation

Token SSE always emitted for Studio timeline. Wire protocol only forwards when `publish_reply !== false` (default true).
