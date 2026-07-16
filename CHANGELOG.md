# Changelog

## [0.5.1](https://github.com/digitalelvis/neuronai-studio/compare/v0.5.0...v0.5.1) (2026-07-16)

# [0.5.0](https://github.com/digitalelvis/neuronai-studio/compare/v0.4.0...v0.5.0) (2026-07-16)


### Bug Fixes

* **workflows:** bind legacy traces JSON route to StudioRun ([6ab03c2](https://github.com/digitalelvis/neuronai-studio/commit/6ab03c2fdcab9cb79da55c17539ff016afdf749c))


### Features

* **usage:** surface usage analytics in dashboard, debugger, and streams ([ecb0a0b](https://github.com/digitalelvis/neuronai-studio/commit/ecb0a0b2a552c5bf500571049ef7599a0bb0ea21))

# [0.4.0](https://github.com/digitalelvis/neuronai-studio/compare/v0.3.3...v0.4.0) (2026-07-16)


### Features

* **compat:** add Laravel 13 and Livewire 4 support ([1b3b971](https://github.com/digitalelvis/neuronai-studio/commit/1b3b971d0bdbbe818ae9773e8607b3b79b68ca2d))
* **usage:** add cost and parent_run columns to runs and spans ([a906da8](https://github.com/digitalelvis/neuronai-studio/commit/a906da8dbe789c7b417dac2e678813ab131f7119))
* **usage:** add currency and catalog pricing defaults ([ca5b1b9](https://github.com/digitalelvis/neuronai-studio/commit/ca5b1b9f149b5ca3118bf0da0c9a8be4175e9061))
* **usage:** add UsageCostEstimator for config-based pricing ([4a42d72](https://github.com/digitalelvis/neuronai-studio/commit/4a42d7283ad0ff41b2ade63e441d4931b791a0b7))
* **usage:** add UsageRecorder for LLM span metering ([150d738](https://github.com/digitalelvis/neuronai-studio/commit/150d738fec6b878dea79f7d436f8eabf457c0667))
* **usage:** finalize run totals from own spans and children ([7a1db55](https://github.com/digitalelvis/neuronai-studio/commit/7a1db555c48eecf9476dfa5c8d3a800f681d186b))
* **usage:** meter LLM spans through TelemetryTracker ([8142174](https://github.com/digitalelvis/neuronai-studio/commit/8142174ab8d81873d0c2615098e22a0778ba2928))
* **usage:** meter LlmNodeExecutor chat and stream paths ([3fbb780](https://github.com/digitalelvis/neuronai-studio/commit/3fbb780c6cff8ae8452aa788e8dc4555a3ffbaa5))
* **usage:** meter playground and integrate agent streams ([4c21122](https://github.com/digitalelvis/neuronai-studio/commit/4c2112291bd199734f9b7332557f62ee604dd4c3))
* **usage:** pass workflow parent run into AgentNodeExecutor ([f9cedb9](https://github.com/digitalelvis/neuronai-studio/commit/f9cedb99a2501a225c95c39c2b0b5a8d2e2a4b22))
* **usage:** wire AgentRunner metering with parent rollup ([fd69561](https://github.com/digitalelvis/neuronai-studio/commit/fd69561f82fdfde761889fdf4daae7e5e74d2c21))
* **usage:** wire run/span models for cost and parent relations ([0c66e87](https://github.com/digitalelvis/neuronai-studio/commit/0c66e879dfab3c010fb0aa8aea8a644dc072e7e0))

## [0.3.3](https://github.com/digitalelvis/neuronai-studio/compare/v0.3.2...v0.3.3) (2026-07-15)

## [0.3.2](https://github.com/digitalelvis/neuronai-studio/compare/v0.3.1...v0.3.2) (2026-07-15)


### Bug Fixes

* **governance:** update required status checks to align with consolidated CI ([19a5fd6](https://github.com/digitalelvis/neuronai-studio/commit/19a5fd68f99d9dcf2f6bccdc9aa88ecdab70e788))

## [0.3.1](https://github.com/digitalelvis/neuronai-studio/compare/v0.3.0...v0.3.1) (2026-07-13)

# [0.3.0](https://github.com/digitalelvis/neuronai-studio/compare/v0.2.0...v0.3.0) (2026-07-13)


### Bug Fixes

* **release:** disable release-it GitHub plugin in CI ([3f91a56](https://github.com/digitalelvis/neuronai-studio/commit/3f91a56c932d555a04f2013a72602a80f68396a4))
* **workflows:** keep deduplicated slug on canvas auto-save ([4537b20](https://github.com/digitalelvis/neuronai-studio/commit/4537b2074cdc0ccea249d02754262d6fdcaf5632))


### Features

* add unified studio runs, traces, spans, and threads schema and models ([c804450](https://github.com/digitalelvis/neuronai-studio/commit/c8044501074b77f018d6affb45d24757d5dcb11a))
* **canvas:** add fork/join nodes and branch inspector ([9eaea41](https://github.com/digitalelvis/neuronai-studio/commit/9eaea41a748cacdb1becd5b00d9173c948e89065))
* **codegen:** export ParallelEvent subclass for fork/join nodes ([b9dd65b](https://github.com/digitalelvis/neuronai-studio/commit/b9dd65b0b7049d138532fd42d653abccd8e82a27))
* **runtime:** add eloquent persistence for native workflows ([5957a80](https://github.com/digitalelvis/neuronai-studio/commit/5957a8001e3dbc25a37d05401a89e631db4ab0d4))
* **runtime:** add fork/join parallel execution with branch resume ([b7aad34](https://github.com/digitalelvis/neuronai-studio/commit/b7aad345d260480d220da9d354cb6122462bb03c))
* **runtime:** add tool approval pause/resume to workflows ([48e1376](https://github.com/digitalelvis/neuronai-studio/commit/48e137664fac9dd8cabb00f9414c25cdb58b5c01))
* **runtime:** add workflow checkpoints table, model and config ([c7beb60](https://github.com/digitalelvis/neuronai-studio/commit/c7beb60436be0592619e4d0fa12f839921bf4c29))
* **runtime:** cache opt-in nodes with a checkpointing executor ([589a489](https://github.com/digitalelvis/neuronai-studio/commit/589a489ea0c3b74ddb0b2359dbf782d4034051f7))
* **runtime:** stream tokens from agent and llm nodes ([808ce21](https://github.com/digitalelvis/neuronai-studio/commit/808ce21dba04c455aed9c74aa5b213e8b2fc0062))
* **stream-adapters:** add configuration, registry and integration endpoints ([b420bc3](https://github.com/digitalelvis/neuronai-studio/commit/b420bc3b3d6dcd5c6f9a9c6670f960485b952882))
* **stream-adapters:** add Connect Panel for agents and workflows ([cd992d2](https://github.com/digitalelvis/neuronai-studio/commit/cd992d2a8ca15ee3de4c42907aaa95be24e6f16c))
* **stream-adapters:** add studio catalog page and navigation link ([a0295fb](https://github.com/digitalelvis/neuronai-studio/commit/a0295fb63027bf9f67ef5ca78a1b5bc8956c9e52))
* **studio:** add stream toggle to agent and llm nodes ([d563107](https://github.com/digitalelvis/neuronai-studio/commit/d5631076d0f055af1ec56148fc8f0750f9bbf5a2))
* **studio:** add tool approval card and native codegen ([12b8c1b](https://github.com/digitalelvis/neuronai-studio/commit/12b8c1bb8b227f5ca9af50375b501df97d09f15a))
* **templates:** add parallel support triage template pack ([953a58e](https://github.com/digitalelvis/neuronai-studio/commit/953a58e4fa6a7e0a39e084a02ac0d17e197b433a))

# [0.2.0](https://github.com/digitalelvis/neuronai-studio/compare/v0.1.2...v0.2.0) (2026-07-03)


### Bug Fixes

* **ci:** disable composer audit block for Laravel 11 matrix installs ([173dac1](https://github.com/digitalelvis/neuronai-studio/commit/173dac1cca240adfec21dbde53654668ea4e3e45))
* **ci:** disable composer block-insecure for matrix dependency installs ([1cdb3c6](https://github.com/digitalelvis/neuronai-studio/commit/1cdb3c63c0b4e50deb018b65df31f3c64450e6ee))
* **rag:** handle missing store file and empty PDF ingest ([d84cf10](https://github.com/digitalelvis/neuronai-studio/commit/d84cf10449c739fa17aece349df2b57e8f87a3bc))
* **runtime:** persist partial trace steps when workflow runs fail ([5d49e3a](https://github.com/digitalelvis/neuronai-studio/commit/5d49e3a67c05a17730ab25bb95509265199cc5f1))
* **templates:** always load package built-in template paths ([2586795](https://github.com/digitalelvis/neuronai-studio/commit/2586795ac558d415fa34e0e2ec26e4b064d05826))
* **workflows:** reconcile loop guardrail and structured output with cyclic graphs ([51668f2](https://github.com/digitalelvis/neuronai-studio/commit/51668f2298ff01f4a0aede2b03ed15ac6d106b02)), closes [#8](https://github.com/digitalelvis/neuronai-studio/issues/8)


### Features

* **canvas:** add StructuredOutputFields inspector component ([55d3477](https://github.com/digitalelvis/neuronai-studio/commit/55d34774cbed6612b7daaceec905f3128c53a281))
* **canvas:** open trace detail when workflow runs fail ([2974621](https://github.com/digitalelvis/neuronai-studio/commit/297462179a0691a6bdacd7bef055fe4271ea3f32))
* **canvas:** show loop iteration badge and harness tool events ([d2677c5](https://github.com/digitalelvis/neuronai-studio/commit/d2677c59a5e665fd1cd80450324e381d95342a23))
* **canvas:** structured output toggles on LLM and agent nodes ([1e276d6](https://github.com/digitalelvis/neuronai-studio/commit/1e276d69e47e54991937a0642e0a508204288add))
* **codegen:** emit loop continue/exit branches in native export ([da55ffd](https://github.com/digitalelvis/neuronai-studio/commit/da55ffd978b0e10830f2661aab9c69dd201188a2))
* **codegen:** emit structured output for agent nodes ([fa4c2c8](https://github.com/digitalelvis/neuronai-studio/commit/fa4c2c845ab7e877f6b403828893fdc9ac2576ed))
* **codegen:** emit structured() for LLM nodes ([a27f503](https://github.com/digitalelvis/neuronai-studio/commit/a27f503ca00081230732cff2f538ab979fe5e347))
* **rag:** add Studio knowledge base CRUD and rag canvas inspector ([6236d0d](https://github.com/digitalelvis/neuronai-studio/commit/6236d0d4a00ec3e21c9bbb848b5fc7fe5c817430))
* **rag:** complete M1 sprint with codegen, docs, and release prep ([d5a8265](https://github.com/digitalelvis/neuronai-studio/commit/d5a82659e6e846718a5a2c07bd698f93a1f8e8cb))
* **rag:** implement workflow RAG backend (slice 1) ([048ea36](https://github.com/digitalelvis/neuronai-studio/commit/048ea36e58a38edcb7070089e2be753b81f33e00))
* **runtime:** stream tool_call and tool_result from agent nodes ([a2e39b1](https://github.com/digitalelvis/neuronai-studio/commit/a2e39b1d0f055780baf29c5d10c91adeab4aa16b))
* **runtime:** support templated human prompts and state append ([08d9186](https://github.com/digitalelvis/neuronai-studio/commit/08d91865cfb106d8d95a645c11bd993cc6927e34))
* **studio:** expose output classes to workflow canvas config ([787790a](https://github.com/digitalelvis/neuronai-studio/commit/787790a86ec24cb16a82b032fdffc6225050424b))
* **templates:** add autonomous lead qualification workflow ([ea6a9d4](https://github.com/digitalelvis/neuronai-studio/commit/ea6a9d42146b3d29377993a34778770e86930592))
* **templates:** add rag-knowledge-qna workflow starter ([b00c538](https://github.com/digitalelvis/neuronai-studio/commit/b00c5385c435b1d21ba06ae905ed88f0939935e3))
* **templates:** make autonomous lead qualification conversational ([38096f2](https://github.com/digitalelvis/neuronai-studio/commit/38096f2cc9f4452a8b53bf3c4aa9ba5ce2cf8a8f))
* **tools:** add RAG knowledge base tool type in Studio ([2070f7f](https://github.com/digitalelvis/neuronai-studio/commit/2070f7f586919060baf73e4d144ea3469fbc6b81))
* **workflow-editor:** fix multimodal test output and agent canvas labels ([9810556](https://github.com/digitalelvis/neuronai-studio/commit/9810556bf981be1ac972247552c493f1433bcd81))
* **workflows:** add controlled cyclic graphs with loop node ([677b127](https://github.com/digitalelvis/neuronai-studio/commit/677b127af7d00d52c036ad23f54cfdd26249ebfc))
* **workflows:** add OutputClassRegistry for structured output classes ([e843e30](https://github.com/digitalelvis/neuronai-studio/commit/e843e307e2b9c5fa62e6e1a1e7e545a1c5aafe71))
* **workflows:** add structured output backend executors (phase 3) ([d38bc1a](https://github.com/digitalelvis/neuronai-studio/commit/d38bc1a5e903565fbc77e95fb3d8737d09505b13))
* **workflows:** add structured_output_scan_paths config ([a260792](https://github.com/digitalelvis/neuronai-studio/commit/a26079223a7b8d6a90dd7acff7f4eaaa76c30e7e))
* **workflows:** add StructuredOutputResolver ([a10cc31](https://github.com/digitalelvis/neuronai-studio/commit/a10cc312c07d18eaeebd4c0c572508fdd9a47e84))
* **workflows:** add WorkflowStateValue dot-notation helper ([e14fbd0](https://github.com/digitalelvis/neuronai-studio/commit/e14fbd083d072e9e05cb079941b789b0c3b62674))
* **workflows:** run and resume workflows via queue jobs ([eaf9ae9](https://github.com/digitalelvis/neuronai-studio/commit/eaf9ae9df574424e6b90a17782d9a0339438b3e8))
* **workflows:** support dot notation in condition and loop nodes ([9891586](https://github.com/digitalelvis/neuronai-studio/commit/9891586615ddb280a4677652f4c467b023b68701))
* **workflows:** surface structured output validation errors in traces ([c984a79](https://github.com/digitalelvis/neuronai-studio/commit/c984a798f2e3d27148271f9991162dffbf15800b))

## [0.1.2](https://github.com/digitalelvis/neuronai-studio/compare/v0.1.1...v0.1.2) (2026-06-29)

## [0.1.1](https://github.com/digitalelvis/neuronai-studio/compare/v0.1.0...v0.1.1) (2026-06-29)

### BREAKING CHANGE

* Rename Composer package to `digitalelvis/neuronai-studio` and PHP namespace to `DigitalElvis\NeuronAIStudio`.

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

[Unreleased]: https://github.com/digitalelvis/neuronai-studio/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/digitalelvis/neuronai-studio/releases/tag/v0.1.0
