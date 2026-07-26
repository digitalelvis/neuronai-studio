# Tools

## Sources

| Source | When |
|--------|------|
| Builder (PHP body in DB) | Local prototyping → export class |
| Webhook | External HTTP + JSON schema |
| RAG tool | On-demand KB search (`?kind=rag`) |
| Built-in toolkits | calculator, calendar, etc. (config) |
| Scanned PHP | `app/Neuron/Tools/` / codegen |
| MCP | Remote tools — [mcp.md](mcp.md) |

## Workflow

1. Choose type (builder / webhook / rag / CLI make-tool)
2. Define schema + implementation
3. Bind on agent or use **tool** workflow node
4. Export builder tools to PHP for production

## Checklist

- [ ] `NEURONAI_STUDIO_ALLOW_BUILDER_TOOLS` for create/edit builder in non-local if needed
- [ ] CodeGen flags allow export when writing classes
- [ ] RAG tool has `knowledge_base_id`; for fixed graph retrieval prefer RAG **node** — [knowledge.md](knowledge.md)
- [ ] Registry detail: `/tools/registry?ref=...`

## Documentation (canonical)

- [Overview](../../../docs/guides/tools/overview.md)
- [Builder Tools](../../../docs/guides/tools/builder-tools.md)
- [Webhook Tools](../../../docs/guides/tools/webhook-tools.md)
- [Registry & Codegen](../../../docs/guides/tools/registry-and-codegen.md)
- [Make Tool CLI](../../../docs/guides/tools/make-tool-cli.md)
