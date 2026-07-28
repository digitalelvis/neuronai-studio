# Tools Overview

Tools extend agent capabilities beyond text generation. NeuronAI Studio supports database-managed tool types plus built-in toolkits, scanned PHP classes, MCP-exposed tools, and workflow Tool Mode specialists (`NodeAsTool`).

## Tool sources

```mermaid
flowchart TD
    Registry[ToolRegistry]
    Registry --> Builtin[Built-in Toolkits]
    Registry --> DB[DB Tool Definitions]
    Registry --> Scanned[Scanned PHP Classes]
    Registry --> MCP[MCP Server Tools]
    DB --> Builder[Builder Tools]
    DB --> Webhook[Webhook Tools]
    DB --> RAG[RAG Tools]
```

| Source | Type | Created in |
|--------|------|------------|
| Built-in toolkits | Neuron `Toolkit` classes | `config/neuronai-studio.php` |
| Builder tools | PHP invoke body → export to class | Studio UI (local prototyping) |
| Webhook tools | HTTP endpoint + JSON schema | Studio UI |
| RAG tools | Knowledge base search (`KnowledgeBaseTool`) | Studio UI (`?kind=rag`) |
| PHP classes | Neuron `Tool` subclasses | `app/Neuron/Tools/`, codegen, or package demos |
| MCP tools | Remote MCP server | MCP server config |
| Tool Mode specialists | `NodeAsTool` via `node:{id}` refs | Workflow canvas toolset edges |

Runtime resolves bindings through `ToolResolver` with prefixes such as `toolkit:`, `class:`, `tool:db:` / `db:`, `mcp:`, `provider:`, and `node:`.

### Package demo class

`IssueRefundTool` (`class:…\IssueRefundTool`) ships with the package for tool-approval demos — see the [refund-actions-agent template](../templates.md) and [Human-in-the-Loop](../workflows/human-in-the-loop.md#tool-approval). Prefer class-based tools when approval pauses must serialize across resume.

## When to use each type

| Need | Tool type |
|------|-----------|
| Quick custom logic in PHP (local) | [Builder Tool](builder-tools.md) → export class |
| Call an external API | [Webhook Tool](webhook-tools.md) |
| Search a knowledge base on demand | [RAG tool](#rag-knowledge-base-tool) |
| Reusable production class | [Make Tool CLI](make-tool-cli.md) + export |
| Math, calendar, etc. | Built-in toolkit (`calculator`, `calendar`) |
| Filesystem, Telescope, etc. | [MCP Server](../mcp-servers/overview.md) |
| Supervisor calls a specialist agent | [Tool Mode](../workflows/node-types/ai-nodes.md#tool-mode-agent-as-tool) (`node:` / `NodeAsTool`) |

## RAG knowledge base tool

Create a tool with type `rag` to let an agent call `RagRetrievalService` during chat:

1. **Tools** → Create → kind **RAG** (or `/tools/create?kind=rag`)
2. Select `knowledge_base_id` and optional `top_k` / `threshold`
3. Bind the tool on the agent

The LLM passes a `query` string; the tool returns source-prefixed chunks. For fixed graph retrieval (not on-demand), use a [RAG workflow node](../knowledge-bases/retrieval.md) instead.

Full guide: [Knowledge Bases — Agent binding](../knowledge-bases/agent-binding.md).

## Studio routes

| Route | Purpose |
|-------|---------|
| `/neuronai-studio/tools` | List database tools |
| `/neuronai-studio/tools/create` | Create builder tool |
| `/neuronai-studio/tools/create?kind=webhook` | Create webhook tool |
| `/neuronai-studio/tools/create?kind=rag` | Create RAG tool |
| `/neuronai-studio/tools/{id}/edit` | Edit tool |
| `/neuronai-studio/tools/{id}` | View tool details |
| `/neuronai-studio/tools/registry?ref=...` | Tool registry detail |

## Binding tools to agents

Tools are bound to agents by `ref` in the agent editor. Canvas toolset edges add `node:` bindings at workflow runtime. See [Creating Agents](../agents/creating-agents.md#tool-bindings) and [Canvas Editor](../workflows/canvas-editor.md#agent-tools-on-the-canvas).

## Next steps

- [Builder Tools](builder-tools.md)
- [Webhook Tools](webhook-tools.md)
- [Registry & Codegen](registry-and-codegen.md)
- [Tool Mode (AI nodes)](../workflows/node-types/ai-nodes.md#tool-mode-agent-as-tool)
