# Changelog

## [3.3.2](https://github.com/digitalelvis/neuronai-studio/compare/v3.3.1...v3.3.2) (2026-08-12)


### Bug Fixes

* **canvas:** apply condition operator changes as a single patch ([3f0d0b2](https://github.com/digitalelvis/neuronai-studio/commit/3f0d0b2c5b327e3020af77d6b4443d084f614be5))

## [3.3.1](https://github.com/digitalelvis/neuronai-studio/compare/v3.3.0...v3.3.1) (2026-08-12)

# [3.3.0](https://github.com/digitalelvis/neuronai-studio/compare/v3.2.0...v3.3.0) (2026-08-12)


### Features

* **workflows:** harden conditions, add Switch node, and fix state variables ([fd20f44](https://github.com/digitalelvis/neuronai-studio/commit/fd20f44151035bf75aa381691d06e98baa24819b))

# [3.2.0](https://github.com/digitalelvis/neuronai-studio/compare/v3.1.0...v3.2.0) (2026-08-08)


### Features

* **canvas:** show playground initial state in variable picker ([73a19db](https://github.com/digitalelvis/neuronai-studio/commit/73a19dbcb3533fe087efc77231aabb45a8616130))

# [3.1.0](https://github.com/digitalelvis/neuronai-studio/compare/v3.0.0...v3.1.0) (2026-08-08)


### Bug Fixes

* **playground:** restore thread history and auto-scroll chat ([0d51a71](https://github.com/digitalelvis/neuronai-studio/commit/0d51a71a5a6d6887802ee10b7e19317dbefab8a3))


### Features

* **playground:** add workflow thread history endpoint ([e54fda2](https://github.com/digitalelvis/neuronai-studio/commit/e54fda245a2fc9f4fdc2fd63ef2702ebb02abf90))

# [3.0.0](https://github.com/digitalelvis/neuronai-studio/compare/v2.7.0...v3.0.0) (2026-08-08)


* chore(deps)!: require neuron-core/neuron-ai ^3.16 ([b50ae2a](https://github.com/digitalelvis/neuronai-studio/commit/b50ae2a19bf7df20e344422244de70a686bd7937))


### Bug Fixes

* **studio:** scope playground tool events to current turn ([183ba2d](https://github.com/digitalelvis/neuronai-studio/commit/183ba2db3d7c8f1d0e967143ada2d5921fd350d2))
* **studio:** silent-save graph before playground run ([5116b20](https://github.com/digitalelvis/neuronai-studio/commit/5116b20eec61b38035690605abdfa7109dd94956))


### Features

* **studio:** keep events dock closed and show tab counters ([cb1345f](https://github.com/digitalelvis/neuronai-studio/commit/cb1345fbf651ada28f7d86f2cd25528002293630))
* **studio:** seed datetime context and expose attachments in START ([82305c3](https://github.com/digitalelvis/neuronai-studio/commit/82305c322f24430021278dc84c83a7e0afcbb53e))
* **threads:** associate conversations with polymorphic owners ([effc036](https://github.com/digitalelvis/neuronai-studio/commit/effc0367b2ce2abcdccdedee7038525dddff2fba))


### BREAKING CHANGES

* neuron-core/neuron-ai minimum is now ^3.16 (was ^3.15).

# [2.7.0](https://github.com/digitalelvis/neuronai-studio/compare/v2.6.1...v2.7.0) (2026-08-07)


### Bug Fixes

* **canvas:** sync intent and fork edges when handles change ([2e0726a](https://github.com/digitalelvis/neuronai-studio/commit/2e0726a135700318923b3a353f55caec6588abf2))
* **workflows:** persist metadata with saveGraph payload ([14b71d6](https://github.com/digitalelvis/neuronai-studio/commit/14b71d6b91607b5091871ad05e99dd81a278fa0e))


### Features

* **studio:** add global bottom-center toast alerts ([f4f3ea0](https://github.com/digitalelvis/neuronai-studio/commit/f4f3ea05063cf86d8118e92f936311f3c4a0b91f))

## [2.6.1](https://github.com/digitalelvis/neuronai-studio/compare/v2.6.0...v2.6.1) (2026-08-07)

# [2.6.0](https://github.com/digitalelvis/neuronai-studio/compare/v2.5.0...v2.6.0) (2026-08-06)


### Bug Fixes

* **workflows:** reuse workflow thread for Intent Classifier metering ([998d049](https://github.com/digitalelvis/neuronai-studio/commit/998d04983563888b8481763525a603654cec13d3))


### Features

* **agents:** accept slug in AgentRunner::run and document invocation ([a0a4c39](https://github.com/digitalelvis/neuronai-studio/commit/a0a4c398ad5db54ca68ced1ecfd6836375488f1a))
* **canvas:** add IDE-style bottom dock with variable inspect ([1483a29](https://github.com/digitalelvis/neuronai-studio/commit/1483a29a2419e457b1818cd5e152dffb76beb5cb))
* **canvas:** add JSON CodeMirror viewer and persistent playground presets ([2de2c78](https://github.com/digitalelvis/neuronai-studio/commit/2de2c784c9340571893fd86e2ac2276c86830e92))
* **canvas:** redesign workflow nodes with inspector sidebar ([70de130](https://github.com/digitalelvis/neuronai-studio/commit/70de1306db27a42dbba9d52579c60407133267a0))
* **workflows:** add Intent Classifier node with Vision and Memory ([abef008](https://github.com/digitalelvis/neuronai-studio/commit/abef008a21ead1657513300283c2564c40b281a5))
* **workflows:** add Vault API key support to LLM and Intent Classifier ([f8e89a2](https://github.com/digitalelvis/neuronai-studio/commit/f8e89a23d63cc54352dea9e636455dd8d90ec476))
* **workflows:** add visual state variable picker in Studio ([74bdbc7](https://github.com/digitalelvis/neuronai-studio/commit/74bdbc713c5cef74150c1c37ab95835c9a0a9937))

# [2.5.0](https://github.com/digitalelvis/neuronai-studio/compare/v2.4.0...v2.5.0) (2026-08-05)


### Features

* **tools:** inject filtered runtime ToolContext into opt-in tools ([04d7cec](https://github.com/digitalelvis/neuronai-studio/commit/04d7ceccc6681e109b0a8c6257694cb80fcb9b38))

# [2.4.0](https://github.com/digitalelvis/neuronai-studio/compare/v2.3.0...v2.4.0) (2026-08-04)


### Features

* **agents:** improve Agent Form tools panel UX ([4abb2d6](https://github.com/digitalelvis/neuronai-studio/commit/4abb2d6701feaf90b5a9441dbdf1838dac958a12))

# [2.3.0](https://github.com/digitalelvis/neuronai-studio/compare/v2.2.1...v2.3.0) (2026-08-04)


### Bug Fixes

* allow binding credential variable on canvas inline agents ([ce99419](https://github.com/digitalelvis/neuronai-studio/commit/ce994196207c3cfc1290652eb03ec42089b5f7f6))
* **studio-chat:** expand nested run_workflow output in Pretty thread ([e538900](https://github.com/digitalelvis/neuronai-studio/commit/e538900389f7449dbd00f31d152fe6a906d5c45f))


### Features

* **codegen:** export run_workflow Step and Tool Mode bindings ([3716242](https://github.com/digitalelvis/neuronai-studio/commit/371624280e6c846c9aedec2cacd11577b4709578))
* **runtime:** add WorkflowAsTool for run_workflow Tool Mode ([3b9ddfe](https://github.com/digitalelvis/neuronai-studio/commit/3b9ddfe686eb967fa3c0d5423012633fdb8f1157))
* **runtime:** implement run_workflow Step Mode executor ([a2906e1](https://github.com/digitalelvis/neuronai-studio/commit/a2906e1730fc62fc9fe6871ba3296bacd6bf0c82))
* **runtime:** nest workflow runs with parent_run_id and depth stamp ([35f3c61](https://github.com/digitalelvis/neuronai-studio/commit/35f3c618e78ce1525a472d1dfb8b57a91567fe9f))
* **studio:** add run_workflow canvas inspector and Tool Mode UI ([22d726c](https://github.com/digitalelvis/neuronai-studio/commit/22d726c8fa38e08f35b0368e814ea9e385c9daf6))
* **studio:** pass workflowsForCanvas into editor config ([ea412a6](https://github.com/digitalelvis/neuronai-studio/commit/ea412a6dd0bec96accad5b7919fa5591c3520761))
* **studio:** register run_workflow node type meta and i18n ([b0a99be](https://github.com/digitalelvis/neuronai-studio/commit/b0a99be80eeb5d6647264264abc9d5cb6344b606))
* **studio:** validate run_workflow graphs in GraphValidator ([6c1f970](https://github.com/digitalelvis/neuronai-studio/commit/6c1f9705bdf6c62c4d4f9c4c67393d0528949668))

## [2.2.1](https://github.com/digitalelvis/neuronai-studio/compare/v2.2.0...v2.2.1) (2026-07-31)


### Bug Fixes

* **database:** shorten mcp_endpoint_bindings index names for MySQL ([78c78ed](https://github.com/digitalelvis/neuronai-studio/commit/78c78ed39f6a6ccd9af8c9a6fb642cc825aae034))

# [2.2.0](https://github.com/digitalelvis/neuronai-studio/compare/v2.1.0...v2.2.0) (2026-07-30)


### Features

* **mcp:** expose Studio as curated MCP endpoints ([a656784](https://github.com/digitalelvis/neuronai-studio/commit/a656784dcfcc8cc2d3ca202e5925cee9f8f0b9b0))

# [2.1.0](https://github.com/digitalelvis/neuronai-studio/compare/v2.0.3...v2.1.0) (2026-07-29)


### Features

* **i18n:** add locale middleware and translation catalogs ([b8463e9](https://github.com/digitalelvis/neuronai-studio/commit/b8463e90681ce84972c6a419abf2d1b8b05d3e37))
* **i18n:** localize artisan command output ([2657e8a](https://github.com/digitalelvis/neuronai-studio/commit/2657e8ae38fdf4d410cf16fa962799f913f7abe9))
* **i18n:** localize studio canvas chat and forms bundles ([af0e5c8](https://github.com/digitalelvis/neuronai-studio/commit/af0e5c805f845ff1930bb0f856ac8721d2f81d7a))
* **i18n:** translate registry and node display labels ([32bce75](https://github.com/digitalelvis/neuronai-studio/commit/32bce75145490510070c87682920a0fed1bdbcd0))
* **i18n:** wire Blade and Livewire chrome translations ([6ffacf0](https://github.com/digitalelvis/neuronai-studio/commit/6ffacf0422637a4f49dab0eba60a5a3bde30a77c))
* **studio:** add fullscreen expandable editor for long text fields ([9953d4e](https://github.com/digitalelvis/neuronai-studio/commit/9953d4e82333aa3d24ec54ecc25a7c27fa3fbc5d))
* **studio:** align agent node handles to inspector anchors ([f99f6f6](https://github.com/digitalelvis/neuronai-studio/commit/f99f6f6b507de837f9e9d683efbec1fcd7e52b02))
* **studio:** reposition workflow canvas chrome and meta editor ([cd474a5](https://github.com/digitalelvis/neuronai-studio/commit/cd474a575d5308abb6fd3e4eb6028865ced66178))
* **variables:** add Studio Variable Vault with field binding ([167b6ae](https://github.com/digitalelvis/neuronai-studio/commit/167b6aeb262fbce73cbfe88e8e8969afd08b8b74))

## [2.0.3](https://github.com/digitalelvis/neuronai-studio/compare/v2.0.2...v2.0.3) (2026-07-28)


### Bug Fixes

* **database:** widen chat_messages.thread_id for scoped agent keys ([1f875ed](https://github.com/digitalelvis/neuronai-studio/commit/1f875eded58533200b97a7c7902b3e8b902af3f1))
* **playground:** generate UUID threads on HTTP and surface stream errors ([4822546](https://github.com/digitalelvis/neuronai-studio/commit/4822546b2f98132e4e3a9da5db56e11aec3b6a72))

## [2.0.2](https://github.com/digitalelvis/neuronai-studio/compare/v2.0.1...v2.0.2) (2026-07-28)


### Bug Fixes

* **runtime:** mark playground runs failed when setup throws ([b79d163](https://github.com/digitalelvis/neuronai-studio/commit/b79d163186aa240a2e1af5b6b58c6a63654ba2a1))
* **studio:** stop Livewire create pages returning 404 ([dc5489d](https://github.com/digitalelvis/neuronai-studio/commit/dc5489d2eaf52b6bc3c3f8ee83025f715a8989e3))

## [2.0.1](https://github.com/digitalelvis/neuronai-studio/compare/v2.0.0...v2.0.1) (2026-07-28)


### Bug Fixes

* **database:** shorten agent_mcp_server unique index name for MySQL ([2f9f656](https://github.com/digitalelvis/neuronai-studio/commit/2f9f65661af61d2e67ae42813a06ef36c5c33156))

# [2.0.0](https://github.com/digitalelvis/neuronai-studio/compare/v1.1.0...v2.0.0) (2026-07-27)


* feat(database)!: prefix all package tables ([a1abe64](https://github.com/digitalelvis/neuronai-studio/commit/a1abe644627f3368998c0fdc72a3dee6967d33bb))


### BREAKING CHANGES

* agent_definitions, workflow_definitions, tool_definitions, mcp_servers, agent_mcp_server, knowledge_bases, and knowledge_documents are now prefixed. Fresh installs get prefixed names; existing DBs need migrate:fresh or a rename migration.

# [1.1.0](https://github.com/digitalelvis/neuronai-studio/compare/v1.0.0...v1.1.0) (2026-07-26)


### Bug Fixes

* **canvas:** auto-layout overlapping nodes on graph load ([a1f81c4](https://github.com/digitalelvis/neuronai-studio/commit/a1f81c47bd3ba5bba7f8d4812aa3cb1959bf42a8))
* **templates:** recalibrate workflow node positions for current size ([dcaee13](https://github.com/digitalelvis/neuronai-studio/commit/dcaee13b7915dd5afa7c0cd70373eafe05d823da))


### Features

* **canvas:** add Tool Mode UI and Actions exposure modal ([a37869b](https://github.com/digitalelvis/neuronai-studio/commit/a37869b1c9dcd639b4ab4feb0080ba546e5fa3b7))
* **canvas:** add Tools/MCP catalogs and tool Actions modal ([6126f40](https://github.com/digitalelvis/neuronai-studio/commit/6126f40906ea40686ee559b8369d4f00a52b60af))
* **canvas:** expose toolable meta for agent node types ([a6ae46d](https://github.com/digitalelvis/neuronai-studio/commit/a6ae46d1591951414192dffbe3fc0432cdaae48a))
* **codegen:** snapshot Tool Mode specialists into supervisor tools ([1a2f27b](https://github.com/digitalelvis/neuronai-studio/commit/1a2f27be3c33011c8c327b6f799e6a0d892c89da))
* **runtime:** collect toolset edges as node tool bindings ([4111a9f](https://github.com/digitalelvis/neuronai-studio/commit/4111a9fc56c3c138bfb06570039121d3b95630b0))
* **runtime:** merge canvas tool bindings with agent definition tools ([034aa60](https://github.com/digitalelvis/neuronai-studio/commit/034aa6069a957734a958fa80bfdb2e7325f0c2d8))
* **runtime:** resolve node tool bindings via NodeAsTool ([77c386c](https://github.com/digitalelvis/neuronai-studio/commit/77c386c1e7e5b5ddd2d51875e719f298c2ad5951))
* **runtime:** validate Tool Mode agents and toolset edges ([4b35050](https://github.com/digitalelvis/neuronai-studio/commit/4b350509e83a0010990c9af774aca26dcba60df3))

# [1.0.0](https://github.com/digitalelvis/neuronai-studio/compare/v0.10.0...v1.0.0) (2026-07-25)


* refactor(deps)!: require neuron-core/neuron-ai instead of neuron-laravel ([80d4880](https://github.com/digitalelvis/neuronai-studio/commit/80d4880804152791dff658fa7e3f3ae06fb60b62))


### Bug Fixes

* **canvas:** widen Share panels and register stream-adapters ([9bee8a1](https://github.com/digitalelvis/neuronai-studio/commit/9bee8a12dced8389980e77355cf0034701d8ea47))
* **codegen:** align exporter tests with agent validation and approval ([efe3ef2](https://github.com/digitalelvis/neuronai-studio/commit/efe3ef2618b5140566bb1915ddd5d2dde1518f56))


### Features

* **canvas:** bind tools to agents via edges and inline agent config ([e5940e9](https://github.com/digitalelvis/neuronai-studio/commit/e5940e9981ed4c5cece0a4f362a75fad7b74dad3))
* **canvas:** redesign workflow studio with Langflow-level UX ([6e1187c](https://github.com/digitalelvis/neuronai-studio/commit/6e1187c6920c64acf233e5d74944a2e9350994a1))
* **codegen:** gate export and preview behind local-only flags ([df34f2b](https://github.com/digitalelvis/neuronai-studio/commit/df34f2b5f93c61eca133d5e4b2bafa10543725d1))
* **playground:** add Langflow-like shell with sessions and traces ([0b1dd71](https://github.com/digitalelvis/neuronai-studio/commit/0b1dd7185ab55b836c6972b1e53d134556adefc9))
* **rag:** expand Neuron vector stores and harden knowledge bases ([cb3a829](https://github.com/digitalelvis/neuronai-studio/commit/cb3a82975cd8f5ef460a46e80225284b41b8f38c))
* **tools:** gate builder tools and fail closed without class_path ([eb9566d](https://github.com/digitalelvis/neuronai-studio/commit/eb9566d5c8d7cec35a5a829200686b46912122b3))


### BREAKING CHANGES

* neuron-core/neuron-laravel is no longer a package
dependency. Host apps should use neuron-core/neuron-ai and config/neuron.php
published by neuronai-studio:install.

# [0.10.0](https://github.com/digitalelvis/neuronai-studio/compare/v0.9.0...v0.10.0) (2026-07-22)


### Features

* **canvas:** expose invoke node inspector and defaults ([5aeb4a8](https://github.com/digitalelvis/neuronai-studio/commit/5aeb4a8de66452c4f86efd84d9cdaf1a1db659e6))
* **codegen:** emit invoke node host hook calls ([5f8e248](https://github.com/digitalelvis/neuronai-studio/commit/5f8e248f0fe57d20543e2d51a73954bcf160aab2))
* **runtime:** add allowlisted invoke workflow node ([1b018ef](https://github.com/digitalelvis/neuronai-studio/commit/1b018efac5c93de0074d992a2dbdbf7762f6ca1e))

# [0.9.0](https://github.com/digitalelvis/neuronai-studio/compare/v0.8.1...v0.9.0) (2026-07-22)


### Bug Fixes

* **workflow:** re-prompt Human nodes on loop revisits ([8fe86e1](https://github.com/digitalelvis/neuronai-studio/commit/8fe86e1f4623b87a375ee10524f70a1a9886fd16))


### Features

* **context:** add TokenBudgetTruncator for prompt assembly budgets ([30a603c](https://github.com/digitalelvis/neuronai-studio/commit/30a603cb7ae2e292bc6394f52fe2cae4dc973e49))
* **context:** apply RAG token budget to rag_context interpolation ([d299157](https://github.com/digitalelvis/neuronai-studio/commit/d299157bf3d74d7269457db8e5abf8502d3e32f3))
* **context:** cap tool results before they re-enter history ([904a932](https://github.com/digitalelvis/neuronai-studio/commit/904a9322db2f331d053ae6f0ac9bd408fa1a54bb))
* **context:** emit budget overrides in agent node codegen ([fe4b5d2](https://github.com/digitalelvis/neuronai-studio/commit/fe4b5d288d047b3d5c91569e4c26dd3265e8df80))
* **context:** expose prompt assembly budgets in agent and node UI ([e46ebd4](https://github.com/digitalelvis/neuronai-studio/commit/e46ebd4659907075072a10ff8178a41756364573))
* **context:** record truncation events as context trace spans ([d0ee0b3](https://github.com/digitalelvis/neuronai-studio/commit/d0ee0b3f2704bf092b07b5a2ebb8a352625487fc))
* **memory:** add agent-node memory overrides in the canvas inspector ([d896a67](https://github.com/digitalelvis/neuronai-studio/commit/d896a672ee6196c778af1386aaaaf057e530de55))
* **memory:** add HistorySummarizer with dedicated model fallback ([8300471](https://github.com/digitalelvis/neuronai-studio/commit/8300471dde2bb1c4d328b55f88d0fe2d6f5f8018))
* **memory:** add MemoryConfig envelope schema and validation ([8b37d5b](https://github.com/digitalelvis/neuronai-studio/commit/8b37d5b0e5c65118d67379076d8fddb8229c6cdf))
* **memory:** compact over-budget history into a persisted summary ([a2d3062](https://github.com/digitalelvis/neuronai-studio/commit/a2d30623f7c520fd814e1fad4549b041e621ff46))
* **memory:** expose memory controls on the agent editor form ([cd3ddd3](https://github.com/digitalelvis/neuronai-studio/commit/cd3ddd3d751291ddfc476fe005cd00a7165e29a7))
* **memory:** honor in_memory driver even with thread id ([e04002e](https://github.com/digitalelvis/neuronai-studio/commit/e04002ee6e8b24b9ad72b77fb6e6ba7bebc7d10c))
* **memory:** non-destructive history trim for Studio chats ([97876f5](https://github.com/digitalelvis/neuronai-studio/commit/97876f53a1c1a447263f021fb37472af50500545))
* **memory:** record history compaction spans when native tracing is on ([ee12d03](https://github.com/digitalelvis/neuronai-studio/commit/ee12d036cd95846c67d1675b747fc3dcf43220e8))
* **memory:** resolve memory_config in AgentRunner and node overrides ([31d3c2a](https://github.com/digitalelvis/neuronai-studio/commit/31d3c2ac69d9a3fd15da1eb612fedf1ea6998ef3))
* **runtime:** catch tool approval inside fork branches ([b718fe7](https://github.com/digitalelvis/neuronai-studio/commit/b718fe73fb23c94a6bcbde3fc7d32453d7eff311))
* **runtime:** extend parallel interrupt for tool approval ([bd7b1e1](https://github.com/digitalelvis/neuronai-studio/commit/bd7b1e107a7ee53d036316ff7162cacd92d3a875))
* **runtime:** pause and resume parallel tool approval ([e656fe8](https://github.com/digitalelvis/neuronai-studio/commit/e656fe8114f384362060cdbcf0c80f02c4ca0dd6))
* **runtime:** preserve sibling results on parallel interrupts ([13ded6d](https://github.com/digitalelvis/neuronai-studio/commit/13ded6d13b96126ebb4455eb9f2162b307587279))
* **templates:** add Dev Support Memory Loop reference workflow ([a5cb98c](https://github.com/digitalelvis/neuronai-studio/commit/a5cb98c43383e6f9df97e53c7c7cef8a0e2c0c2c))
* **templates:** add parallel-refund-approval workflow ([1f8cdd3](https://github.com/digitalelvis/neuronai-studio/commit/1f8cdd3fb4199631c1d1bbd1c34130a710b938ce))
* **templates:** add refund-actions-agent with tool approval ([5ea28c7](https://github.com/digitalelvis/neuronai-studio/commit/5ea28c70f9f444f8ff7888db50c026f11e14fe1c))
* **templates:** persist require_tool_approval on agent install ([fdd1369](https://github.com/digitalelvis/neuronai-studio/commit/fdd13696f606317db36fbfc4dae49b71aa7428f5))
* **tools:** add class-based IssueRefundTool for approval demos ([13a033e](https://github.com/digitalelvis/neuronai-studio/commit/13a033ee328a38b9eb48ad220fb9a042b7dabf8e))

## [0.8.1](https://github.com/digitalelvis/neuronai-studio/compare/v0.8.0...v0.8.1) (2026-07-20)

# [0.8.0](https://github.com/digitalelvis/neuronai-studio/compare/v0.7.0...v0.8.0) (2026-07-20)


### Bug Fixes

* **observability:** drive Langfuse via client and map thread sessions ([32a526b](https://github.com/digitalelvis/neuronai-studio/commit/32a526bf26aede3e1acf559f6b9479f2e5958a81))


### Features

* **observability:** add observability config section ([c6bb7be](https://github.com/digitalelvis/neuronai-studio/commit/c6bb7bea8bd301f52dd904a0fb722073c3133947))
* **observability:** add ObservabilityManager and Langfuse adapter ([00f7624](https://github.com/digitalelvis/neuronai-studio/commit/00f762442b97c386f64b7e068a045d69097bf303))
* **observability:** wire Manager into runners and LLM nodes ([4fd5330](https://github.com/digitalelvis/neuronai-studio/commit/4fd53303a0bb0c5fc00c6b82b7f7fedd874655ef))

# [0.7.0](https://github.com/digitalelvis/neuronai-studio/compare/v0.6.0...v0.7.0) (2026-07-17)


### Features

* **runtime:** ship M6 agent tool controls, async progress, concurrent fork ([fad9777](https://github.com/digitalelvis/neuronai-studio/commit/fad97775bf7e1d1e46d3e2532a7246fe2689caab))

# [0.6.0](https://github.com/digitalelvis/neuronai-studio/compare/v0.5.1...v0.6.0) (2026-07-16)


### Features

* **usage:** add host metering export API and usage events ([7bbe343](https://github.com/digitalelvis/neuronai-studio/commit/7bbe343807b0f17228e03a77b08b119839a916eb))

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
