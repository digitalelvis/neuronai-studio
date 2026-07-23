# Security & Access

NeuronAI Studio includes gate-based authorization, middleware protection, and security controls for webhook and MCP integrations.

## Authorization gate

All studio routes pass through the `neuronai-studio.auth` middleware, which checks the configured gate.

```php
// config/neuronai-studio.php
'gate' => 'viewNeuronAIStudio',
'middleware' => ['web', 'neuronai-studio.auth'],
```

### Default behavior

| Environment | Access |
|-------------|--------|
| `local` | Open to all (no auth required) |
| Other | Requires authenticated user |

### Custom gate

Define in `AppServiceProvider`:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewNeuronAIStudio', function ($user) {
    return $user->hasRole('admin');
});
```

```mermaid
flowchart LR
    Request[HTTP Request] --> Middleware[neuronai-studio.auth]
    Middleware --> Gate{viewNeuronAIStudio?}
    Gate -->|yes| Studio[Studio UI]
    Gate -->|no| Deny[403 Forbidden]
```

## Webhook security

Webhook tools validate target hosts against an allowlist:

```env
NEURONAI_STUDIO_WEBHOOK_ALLOWED_HOSTS=api.example.com,hooks.slack.com
```

Default `*` allows all hosts — **change this in production** to prevent SSRF.

Timeout limit:

```env
NEURONAI_STUDIO_WEBHOOK_TIMEOUT=15
```

## MCP security

### Stdio command allowlist

Only commands in the allowlist can spawn MCP processes:

```php
'mcp_stdio_allowlist' => ['npx', 'node', 'python', 'python3', 'uv', 'uvx'],
```

An empty array allows all commands.

### HTTP token auth

HTTP MCP servers use `token_env` to reference bearer tokens from `.env` — never hardcode secrets in config files.

## Invoke node hooks

The canvas **Invoke** node may only call FQCNs listed in `invoke_hooks` (empty = nothing allowed):

```php
'invoke_hooks' => [
    \App\Neuron\Hooks\EnrichLead::class,
],
```

Each class must be resolvable from the container and implement `__invoke(\NeuronAI\Workflow\WorkflowState $state): mixed`. See [Logic Nodes — Invoke](workflows/node-types/logic-nodes.md#invoke).

## Approving sensitive tools

Agents can call tools that perform destructive or high-impact actions (deleting files, transferring money, sending emails). **Tool approval** adds a human gate: when enabled on an agent (or overridden on a workflow Agent node), execution pauses before any tool runs and requires an explicit approve/reject decision in the workflow test harness.

| Control | Recommendation |
|---------|----------------|
| Destructive tools | Enable **Require tool approval** on agents that can delete, pay, or externally message |
| Review payload | Inspect the tool name and arguments in the approval card before approving |
| Rejections | Wire a `rejected` handle on the Agent node so denied actions branch to a safe path instead of silently continuing |
| Tool implementation | Use class-based tools — the paused interrupt is serialized into the checkpoint and inline `Closure` callbacks cannot be serialized |

> Approval currently gates **all** tools an agent requests (there is no per-tool allowlist yet), so enable it for agents whose entire tool surface warrants oversight.

See [Human-in-the-Loop → Tool approval](workflows/human-in-the-loop.md#tool-approval) and [Creating Agents → Tool approval](agents/creating-agents.md#tool-approval).

## Production recommendations

| Control | Recommendation |
|---------|----------------|
| Studio access | Restrict gate to admin/developer roles |
| Environment | Disable open access outside `local` |
| CodeGen | Keep `codegen.*` off outside local (or enable selectively); see [Export & Production](export-and-production.md#codegen-feature-flags) |
| Webhooks | Set explicit host allowlist |
| MCP stdio | Keep command allowlist minimal |
| Invoke hooks | Keep `invoke_hooks` minimal (fail-closed) |
| Attachments | Use dedicated disk with size limits |
| API keys | Keep in `config/neuron.php` / `.env` only |
| Sensitive tools | Require tool approval for destructive agent actions |

## CodeGen

Code generation (export to disk, class preview, `make-tool`, import-to-studio) is controlled by `neuronai-studio.codegen` flags. Defaults are on only in `local`. In staging/production leave them off unless you intentionally allow developers to write PHP under `export_path`.

Already-exported classes continue to resolve at runtime regardless of these flags.

## File uploads

Attachment uploads respect:

- `max_size_kb` — default 10 MB
- `allowed_mimes` — restricted file types
- Laravel disk configuration

## Related code

- `EnsureNeuronAIStudioAuthorized` middleware
- `NeuronAIStudioServiceProvider::registerGate()`
- `WebhookTool` — host validation

## See also

- [Installation](../getting-started/installation.md#authorization)
- [Webhook Tools](tools/webhook-tools.md)
- [MCP Stdio & HTTP](mcp-servers/stdio-and-http.md)
