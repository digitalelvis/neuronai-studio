# Database Schema

NeuronAI Studio stores definitions and runtime data in prefixed database tables.

## Table prefix

Default: `neuronai_studio_`

Configure with `NEURONAI_STUDIO_TABLE_PREFIX`.

## Tables

| Table | Purpose |
|-------|---------|
| `agent_definitions` | Agent name, provider, model, instructions, tool bindings |
| `workflow_definitions` | Workflow name, graph JSON, code source metadata |
| `tool_definitions` | Builder and webhook tool configs |
| `mcp_servers` | MCP server transport configuration |
| `agent_mcp_server` | Agent ↔ MCP server pivot with filters |
| `workflow_traces` | Workflow execution records |
| `workflow_trace_steps` | Per-step input/output timeline |
| `chat_messages` | Persisted agent playground messages |
| `eval_suites` | Agent evaluation datasets and judge config |
| `eval_runs` | Evaluation execution records |
| `eval_run_items` | Per-case results (input, output, pass/fail) |

## Entity relationships

```mermaid
erDiagram
    agent_definitions ||--o{ agent_mcp_server : binds
    mcp_servers ||--o{ agent_mcp_server : exposes
    workflow_definitions ||--o{ workflow_traces : produces
    workflow_traces ||--o{ workflow_trace_steps : contains
    agent_definitions ||--o{ chat_messages : threads
    agent_definitions ||--o{ eval_suites : has
    agent_definitions ||--o{ eval_suites : judges
    eval_suites ||--o{ eval_runs : produces
    eval_runs ||--o{ eval_run_items : contains
```

## Key columns

### agent_definitions

- `slug` — unique identifier, used in templates and exports
- `provider`, `model` — LLM configuration
- `instructions` — system prompt
- `tools` — JSON tool binding array

### workflow_definitions

- `graph` — JSON canvas (nodes, edges, viewport)
- `code_source` — optional PHP class reference for imported workflows

### workflow_traces

- `status` — running, completed, failed, waiting_for_human
- `checkpoint` — serialized state for HITL resume

### eval_suites

- `agent_definition_id` — agent under test
- `judge_agent_definition_id` — optional Studio agent used as AI judge
- `slug` — unique per agent
- `dataset` — JSON array of test cases (`input`, `reference`, `context`, `_assertions`, `tool`)
- `judge_config` — deprecated inline judge provider/model/instructions (prefer `judge_agent_definition_id`)

### eval_runs

- `status` — running, completed, failed
- `passed_count`, `failed_count`, `success_rate` — aggregated from `EvaluatorSummary`
- `provider`, `model` — snapshot of agent under test at run time
- `judge_agent_definition_id`, `judge_provider`, `judge_model` — snapshot of judge agent at run time

### eval_run_items

- `case_index` — dataset item index
- `input`, `output` — case data
- `passed` — boolean result
- `failures`, `scores` — JSON from NeuronAI assertion results

## Migrations

Migrations load automatically from the package. Publish only if you need to modify them:

```bash
php artisan vendor:publish --tag=neuronai-studio-migrations
```

## Related code

- `src/Support/StudioTables.php` — table name helper
- `database/migrations/` — migration files
