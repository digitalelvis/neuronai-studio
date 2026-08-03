# Quick: Canvas agent credential variable

## Problem
Inline agents ("Configure on canvas") in Workflow Studio have no UI to bind an API key / Credential variable (`var:NAME`). Runtime fails when install-time provider credentials are empty.

## Done when
- [ ] Canvas config exposes Studio variables (name + type)
- [ ] Inline agent form shows API Key field with VariableInput (same as Agent form)
- [ ] `api_key` is stored on node data and used by existing AgentRunner/ProviderRegistry path
- [ ] Canvas bundle rebuilt
- [ ] Committed on current branch

## Out of scope
- LLM node credential override
- Backend runtime changes (already supports `api_key` + `var:NAME`)
