# Summary: Canvas agent credential variable

## What changed
- Workflow editor now passes Studio variables into canvas config.
- Inline agent ("Configure on canvas") shows **API Key (optional override)** with VariableInput (`var:NAME`).
- Runtime already resolved `api_key` via AgentRunner → ProviderRegistry → ConfigValueResolver; no PHP runtime change needed.
- Canvas + forms bundles rebuilt.

## Verify
1. Open Workflow Studio → Agent node → Configure on canvas.
2. Confirm API Key field appears under Model.
3. Bind a Credential variable (or paste key) → save → run workflow.

## Commit
`fix: allow binding credential variable on canvas inline agents`
