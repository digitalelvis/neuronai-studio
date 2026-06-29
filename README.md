# NeuronAI Studio

[![Documentation](https://img.shields.io/badge/docs-GitBook-blue)](https://YOUR_ORG.gitbook.io/neuronai-studio)

Visual AI Agent Builder for Laravel powered by [Neuron AI](https://neuron-ai.dev).

Create agents, design workflow graphs, run them at runtime for prototyping, and export production-ready PHP classes.

## Documentation

Full documentation is available on GitBook:

**[docs/README.md](docs/README.md)** — local docs source (syncs to GitBook on push to `main`)

Quick links:

- [Installation](docs/getting-started/installation.md)
- [Quickstart: First Agent](docs/getting-started/quickstart-first-agent.md)
- [Quickstart: First Workflow](docs/getting-started/quickstart-first-workflow.md)
- [Configuration](docs/reference/configuration.md)

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- [neuron-core/neuron-laravel](https://github.com/neuron-core/neuron-laravel) ^1.0

## Quick install

```bash
composer require elvislopesdigital/neuronai-studio neuron-core/neuron-laravel
php artisan neuron:install
php artisan neuronai-studio:install
```

Open `/neuronai-studio` (configurable via `NEURONAI_STUDIO_ROUTE_PREFIX`).

## Features

- **Agents** — CRUD with provider, model, system prompt, tools, and MCP bindings
- **Playground** — Streaming chat with threads and attachments
- **Workflows** — Visual graph editor with 12 node types
- **Tools** — Builder, webhook, registry, and CLI codegen
- **MCP Servers** — Stdio and HTTP connectors
- **Runtime** — Execute workflows with traces and human-in-the-loop
- **Export** — Generate Neuron Agent/Workflow/Tool PHP classes
- **Templates** — Pre-built agent and workflow starters

## Demo App

See [examples/demo-app](examples/demo-app) for a local development setup.

## Contributing

Contributions are welcome! See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT — see [LICENSE](LICENSE).
