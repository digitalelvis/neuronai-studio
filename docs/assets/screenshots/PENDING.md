# Screenshot capture checklist

All documentation pages reference screenshots with `<!-- SCREENSHOT: tag -->` comments. Capture each asset and save it to `docs/assets/screenshots/{tag}.png`.

## Style guide

- **Resolution:** 1440×900 (or 2× retina equivalent)
- **Theme:** Dark mode (consistent across all captures)
- **Browser:** Hide personal bookmarks; use a clean window or crop to content area
- **Data:** Use demo-friendly names (no real API keys or customer data)

## Pending captures

| Status | Tag | Page | Capture instructions |
|--------|-----|------|----------------------|
| [ ] | `dashboard-overview` | Introduction | `/neuronai-studio` — stats cards + recent traces |
| [ ] | `install-success-dashboard` | Installation | Dashboard immediately after first install |
| [ ] | `agents-index` | Agents overview | Agent list with Create Agent button |
| [ ] | `agents-form` | Creating agents | Agent editor: provider, model, instructions, tool bindings |
| [ ] | `agents-playground` | Playground | Streaming chat with an expanded tool-call panel |
| [ ] | `agents-thread-bar` | Threads | Thread selector showing multiple conversation threads |
| [ ] | `agents-attachments` | Attachments | Composer with a PDF or image attached |
| [ ] | `agents-evals-index` | Evaluations | Eval suites list for an agent |
| [ ] | `agents-evals-run-detail` | Evaluations | Run detail with pass/fail cases |
| [ ] | `tools-builder` | Builder tools | Tool builder with PHP invoke preview |
| [ ] | `tools-webhook` | Webhook tools | Webhook config + JSON schema editor |
| [ ] | `tools-registry` | Registry | Unified tool catalog (builtin + DB + MCP) |
| [ ] | `mcp-servers-list` | MCP overview | MCP server index page |
| [ ] | `mcp-servers-edit` | Stdio/HTTP | Server editor with test discovery button |
| [ ] | `workflows-canvas` | Canvas editor | Full graph with node palette + inspector |
| [ ] | `workflows-inspector-agent` | AI nodes | Agent node inspector fields |
| [ ] | `workflows-inspector-condition` | Logic nodes | Condition node with true/false handles |
| [ ] | `workflows-test-harness` | Runtime | Test harness running a workflow |
| [ ] | `workflows-traces-list` | Traces | Trace list for a workflow |
| [ ] | `workflows-trace-detail` | Traces | Step timeline with input/output expanded |
| [ ] | `workflows-hitl` | HITL | Paused human node + resume UI |
| [ ] | `workflows-code-panel` | Export | PHP code preview panel in workflow editor |
| [ ] | `templates-gallery` | Templates | Template browser with type/complexity filters |

## Optional

| Status | Tag | Page | Capture instructions |
|--------|-----|------|----------------------|
| [ ] | `export-cli-output` | Export | Terminal showing `neuronai-studio:export` output (screenshot or keep as code block) |
| [ ] | `published-views-warning` | Installation | Outdated UI caused by published view overrides (troubleshooting doc) |

## After capturing

1. Save PNG to `docs/assets/screenshots/{tag}.png`
2. Check the box in this file
3. Remove the "Screenshot pending" callout from the corresponding doc page (optional once image loads)
