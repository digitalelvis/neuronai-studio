# JEV Classifier Tasks

**Spec:** `.specs/features/jev-classifier/spec.md`  
**Design:** `.specs/features/jev-classifier/design.md`

| ID | Task | Done when | Status |
| --- | --- | --- | --- |
| JEV-T1 | Bump `neuron-ai` to `^3.17` and require `neuron-core/router` `1.x-dev#dbef16f7` | Classes `TypeSafeAI`, `FakeClassifier`, `DifficultyRule` autoload | done |
| JEV-T2 | `ClassifierRegistry` + `classifier` config | Missing key throws; bound `ClassifierInterface` wins | done |
| JEV-T3 | Intent engine `jev` + codegen + validator + inspector | FakeClassifier tests; LLM path unchanged | done |
| JEV-T4 | `routing_config` migration, factory, `AgentRunner`, forms, exporter | Three-tier test, fail-closed, export contains `TypeSafeAI` | done |
| JEV-T5 | Docs (`installation`, `creating-agents`, `ai-nodes`, `configuration`) | `TYPESAFE_KEY` documented as opt-in | done |

P2 (JEV-07) is not in this task list.
