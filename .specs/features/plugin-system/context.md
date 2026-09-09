# Plugin System — Context

**Gathered:** 2026-09-08  
**Spec:** `.specs/features/plugin-system/spec.md`  
**Status:** Ready for design

---

## Feature Boundary

Closed-catalog connector packs for NeuronAI Studio hosts. Reuses M21 skills runtime and existing MCP inbound stack. Not a Cursor-style open marketplace.

## Locked Decisions

| Area | Decision |
|------|----------|
| Plugin format | Claude `.claude-plugin/plugin.json` + `skills/` + `.mcp.json` |
| Trust | Default `closed`; `allowlist` for host extras; `open` deferred |
| Accounts | Studio layer; manifest only declares env key names |
| Materialization | Creates `SkillDefinition` + `McpServer`; agent uses existing pivot/refs |
| OAuth | Out of P1; host implements in P3 |

## Deferred Ideas

- Public plugin registry with publisher verification
- Per-tool toggle UI (P2)
- Canvas plugin node (P2 optional)
