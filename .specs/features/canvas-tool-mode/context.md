# Canvas Tool Mode — Context

**Opened:** 2026-07-25  
**Source:** Analysis of Neuron AI multi-agent + Langflow Tool Mode screenshots (agent Step vs Tool Mode → Actions modal → Toolset handle).

## Locked decisions

| ID | Decision |
|----|----------|
| D1 | **Tool Mode** is a **component capability** (`toolable` meta), not Agent-only special case. |
| D2 | **v1** enables Tool Mode only for node type `agent`. Contract must accept future `llm` / `rag` / custom. |
| D3 | Duality: `tool_mode: false` = Step (Input + Response); `tool_mode: true` = Tool (Actions + Toolset). |
| D4 | Exposure schema: `slug`, `description`, params with primary input **controlled by caller**. |
| D5 | Binding ref: `node:{canvasNodeId}` resolved via `tool_exposure` + node config. |
| D6 | Tool-mode nodes are **binding-only** — excluded from control-flow walk (same class as today's `tools` edges). |
| D7 | Supervisor **merges** definition tools + toolset bindings (existing agents keep AgentDefinition tools; canvas specialists append). |
| D8 | Supervisor `tools` target handle visible whenever node can receive toolset (inline **and** existing). |
| D9 | Nested call metering: child run `parent_run_id` under workflow / supervisor visit (reuse nested agent pattern). |
| D10 | Neuron has no SubAgent API — implement as Studio `Tool` wrapper (`NodeAsTool` / Agent branch). |

## Explicitly deferred (out of v1)

- LLM / RAG / Invoke as toolable
- Irreversible handoff protocol
- ~~Nested workflow-as-tool~~ → **promoted:** feature [`execute-workflow`](../execute-workflow/spec.md) (M13 / AD-030) — Step + Tool Mode `run_workflow`
- Fancy memory isolation (default: in-memory or child thread per call)
- Parallel fan-out of multiple tool-mode agents from one tool-call batch (Neuron parallel tools if already on)

## Related

- Plan: `.cursor/plans/subagent_delegation_analysis_ef0e1041.plan.md` (session)
- Prior canvas tools: M9 slice 26 (tool/mcp → agent `tools`)
- Metering: AD-014 nested agent under workflow
