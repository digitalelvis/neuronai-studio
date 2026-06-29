# Changelog

# 0.1.0 (2026-06-29)


### Bug Fixes

* **canvas:** improve workflow editor connections and agent config sync. ([7adc4c1](https://github.com/digitalelvis/neuronai-studio/commit/7adc4c1c98049ea708d77b3b3aaf8536d50e71ac))
* **canvas:** restore saved agent selection in workflow inspector. ([ed88eee](https://github.com/digitalelvis/neuronai-studio/commit/ed88eee1f50de8ffdcb3e24f42be56f803b3a79f))
* **docs:** unblock docs CI for pending screenshots and clean up local MCP config ([dda0ba1](https://github.com/digitalelvis/neuronai-studio/commit/dda0ba1a9a27226ca67d5f81e7046f0d5a531591))
* **playground:** honor agent model and import UserMessage ([5336ba7](https://github.com/digitalelvis/neuronai-studio/commit/5336ba7ee87e91de1935564969f56766219a1b54))
* **studio:** apply agent playground context and align input panel with workflow ([1affe0a](https://github.com/digitalelvis/neuronai-studio/commit/1affe0a8a87fbb5290c6d74c5beed409f5defeaf))
* **studio:** keep workflow test tab active and add JSON input in chat ([66a4169](https://github.com/digitalelvis/neuronai-studio/commit/66a4169db9a15d1d766c791df1c945a61d3a0fc3))
* **studio:** lock canvas during workflow test and stabilize test tab ([8d55f1f](https://github.com/digitalelvis/neuronai-studio/commit/8d55f1fefec37a22e18cbbcbbc843408a7145e3a))
* **studio:** prevent flash alerts from breaking flush page layout. ([651abd1](https://github.com/digitalelvis/neuronai-studio/commit/651abd1d45c25f6c69693a0fe1f87512a65b955d))
* **studio:** restore resizable layouts on product pages. ([94a1655](https://github.com/digitalelvis/neuronai-studio/commit/94a16556b6d52f34d9abe3b0fe9bbdbe7bf745f5))


### Features

* **canvas:** replace Alpine canvas with React Flow studio editor ([8b7efb9](https://github.com/digitalelvis/neuronai-studio/commit/8b7efb9fd4c61295bb8dcb0230392c3cf289c9e0))
* **evals:** add Agent as Judge via Studio agents and NeuronAI judges. ([cb89418](https://github.com/digitalelvis/neuronai-studio/commit/cb89418703a723ba2d8c5e95b018627b43d53b29))
* **mcp:** add MCP server management and runtime integration. ([80ab1cb](https://github.com/digitalelvis/neuronai-studio/commit/80ab1cb85f2bfdeb32f78f0dba18d69e8db26af3))
* **skills:** add Neuron AI agent skills for studio development. ([31256d0](https://github.com/digitalelvis/neuronai-studio/commit/31256d0eaface93d34526bca6225136321dbcfc0))
* **studio:** add agent evaluations via NeuronAI Evals ([14d3d81](https://github.com/digitalelvis/neuronai-studio/commit/14d3d8184e8bf6c44c86f4619a0466c1750b8c6a))
* **studio:** add bundled agent and workflow templates ([2209457](https://github.com/digitalelvis/neuronai-studio/commit/22094571e1105905f6c8d534c9734c8fcf71e3fd))
* **studio:** add CodeMirror 6 editors for JSON and PHP surfaces. ([16dc646](https://github.com/digitalelvis/neuronai-studio/commit/16dc646dd863615e6f117034f75decdebb922214))
* **studio:** add LLM provider/model picker and fix template defaults ([2a28a8a](https://github.com/digitalelvis/neuronai-studio/commit/2a28a8a76c97b8a2936652cea4838a88cbdfd596))
* **studio:** add StudioChat test harness for agents and workflows ([f8a29d2](https://github.com/digitalelvis/neuronai-studio/commit/f8a29d2300bb2df4b5225d45a1727431d9d87a24))
* **studio:** add workflow Code tab with live PHP preview. ([c02dd76](https://github.com/digitalelvis/neuronai-studio/commit/c02dd766f38f93cab932cae8469d277ef696e5a9))
* **studio:** add workflow JSON I/O, code bridge, and UI refresh ([93a38d6](https://github.com/digitalelvis/neuronai-studio/commit/93a38d69053f39cf9af563e9e622bfe6eae368f5))
* **studio:** add workflow traces tab and rename runs to traces ([f434298](https://github.com/digitalelvis/neuronai-studio/commit/f434298c305e01c590b1a681df24dd90eda91e9e))
* **studio:** export workflows as native NeuronAI PHP classes. ([be676fd](https://github.com/digitalelvis/neuronai-studio/commit/be676fdcfe4501345ba136831fef572b00139d16))
* **studio:** extend chat threads to workflow test runs ([0431460](https://github.com/digitalelvis/neuronai-studio/commit/0431460154e218ddc2a403a28f6fa969dd16db19))
* **studio:** move node editing to React Flow toolbar and slideover. ([9439b22](https://github.com/digitalelvis/neuronai-studio/commit/9439b227606701e7e8e53b55cc6e114ae2f9a528))
* **studio:** persist agent playground chat threads ([26324e4](https://github.com/digitalelvis/neuronai-studio/commit/26324e42fd9d72529aac234ca31f6e4c93301cde))
* **tools:** add tool registry, builder UI, and agent runtime wiring. ([45fd188](https://github.com/digitalelvis/neuronai-studio/commit/45fd188a8686b8af598b686f52fcdf0890a5df0e))

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Full documentation site under `docs/` with GitBook Git Sync support
- GitHub Actions workflows for docs validation and PHPUnit
- CONTRIBUTING.md, LICENSE, and CHANGELOG.md for open-source contributions
- Feature guides for agents, tools, MCP, workflows, export, and security
- Screenshot placeholder system with capture checklist in `docs/assets/screenshots/PENDING.md`

## [0.1.0] - TBD

### Added

- Initial release of NeuronAI Studio
- Visual agent builder with Playground and streaming chat
- Workflow canvas editor with 12 node types
- Tool builder, webhook tools, and MCP server management
- Workflow runtime with traces and human-in-the-loop
- PHP export for agents, workflows, and tools
- Pre-built agent and workflow templates

[Unreleased]: https://github.com/elvislopesdigital/neuronai-studio/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/elvislopesdigital/neuronai-studio/releases/tag/v0.1.0
