# Dashboard

The dashboard is the landing page when you open NeuronAI Studio. It gives you a quick overview of your studio resources and recent activity.

## URL

```
/neuronai-studio
```

Configurable via `NEURONAI_STUDIO_ROUTE_PREFIX`.

## What you see

| Section | Description |
|---------|-------------|
| **Stats cards** | Resource counts plus total tokens and estimated cost for the last 30 days |
| **Recent traces** | Latest execution records with status, tokens, estimated cost, and start time |
| **Quick navigation** | Sidebar links to all studio sections |

<!-- SCREENSHOT: dashboard-overview -->
> **Screenshot pending:** Dashboard overview with stats cards and recent traces.
>
> Asset path: `docs/assets/screenshots/dashboard-overview.png`
> Capture: `/neuronai-studio` — dark theme, 1440×900

![Dashboard overview](../assets/screenshots/dashboard-overview.png)

## Navigation

The sidebar provides access to:

- **Dashboard** — this page
- **Agents** — create and manage AI agents
- **Workflows** — visual workflow editor and traces
- **Tools** — builder and webhook tools
- **MCP Servers** — Model Context Protocol connectors
- **Templates** — pre-built agent and workflow starters

## When to use the dashboard

Use the dashboard to:

- Confirm your studio is installed and connected to the database
- Jump to recent workflow runs that need investigation
- Monitor recent token use and estimated spend without leaving the landing page
- Get a sense of resource counts before exporting to production

## Related

- [Agents Overview](agents/overview.md)
- [Workflows Overview](workflows/overview.md)
- [Usage Analytics](analytics/usage.md)
