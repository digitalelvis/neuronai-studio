# JEV Classifier Specification

## Problem Statement

Intent classification and model choice in Studio both pay a generative LLM. Intent Classifier uses structured output, and each agent is pinned to one provider/model before the conversation is seen. TypeSafe JEV (Neuron `ClassifierInterface`) returns calibrated probabilities for closed questions fast enough to run on every turn. Studio should use that contract for intent routing and difficulty-based model selection, without a vendor SDK.

## Goals

- [ ] Intent Classifier can use engine `jev` (Choice) and still route exclusive intent handles
- [ ] Agents can route each inference to easy/medium/hard models via `RouterProvider` + `DifficultyRule`
- [ ] Missing TypeSafe credentials fail closed; LLM intent path and single-model agents stay the default

## Out of Scope

| Feature | Reason |
| --- | --- |
| Generic canvas Classifier node (Boolean/Choice/Score) | P2 |
| Prompt-injection guardrail and auto tool-approval | P2 |
| TypeSafe token pricing in M5 metering | P2 — classifier spans record cost 0 |
| Classifier drivers other than TypeSafe | `ClassifierInterface` is the seam; only TypeSafe ships now |
| `butochnikov/laravel-typesafe-jev` or `sanmai/typesafe-ai-php` | Neuron HTTP client already implements the API |

---

## User Stories

### P1: JEV intent engine ⭐ MVP

**User Story**: As a workflow author, I want the Intent Classifier to call JEV instead of an LLM so that N-way routing is cheap and returns a real probability.

**Why P1**: This replaces the most common closed-question LLM call in the canvas.

**Acceptance Criteria**:

1. WHEN engine is omitted or `llm` THEN the node SHALL keep structured-output classification.
2. WHEN engine is `jev` THEN the node SHALL classify with `Choice` options built from intent id → description (name fallback) and SHALL return the winning intent handle.
3. WHEN `min_probability` is set and the winner is below it THEN the node SHALL take `other` when that intent exists, otherwise SHALL fail with a clear error.
4. WHEN memory is on THEN the classifier input SHALL include prior thread messages; WHEN it is off THEN the input SHALL be the interpolated message only.
5. WHEN a workflow trace exists THEN the node SHALL record a `classifier` span with token counts and estimated cost 0.

**Independent Test**: `IntentClassifierNodeExecutorTest` with `FakeClassifier`.

---

### P1: Difficulty routing on agents ⭐ MVP

**User Story**: As an agent author, I want each turn routed to a small, medium, or strong model based on JEV difficulty so that easy messages do not pay for the strongest model.

**Why P1**: One pinned model is wrong for part of every real conversation.

**Acceptance Criteria**:

1. WHEN `routing_config.enabled` is false or null THEN `AgentRunner` SHALL resolve the single provider/model as today.
2. WHEN routing is enabled THEN `AgentRunner` SHALL build `RouterProvider` + `DifficultyRule` over the configured tiers and SHALL reclassify every inference.
3. WHEN no TypeSafe key is resolvable and no test classifier is bound THEN runtime SHALL throw `InvalidArgumentException` and SHALL NOT call a chat provider.
4. WHEN history is empty THEN routing SHALL follow Neuron and pick the most capable configured tier without calling the classifier.
5. WHEN an inference completes THEN the LLM span SHALL use the selected tier provider/model, and a `classifier` span SHALL record the tier (score is not available from the router).
6. WHEN the agent is exported THEN `provider()` SHALL emit `RouterProvider`, `DifficultyRule`, and `TypeSafeAI`.

**Independent Test**: `AgentRunnerRoutingTest` with queued `FakeClassifier` scores and `AgentExporter` snapshot.

---

### P2: Generic classifier node and guardrails

**User Story**: As an author, I want arbitrary Boolean/Choice/Score questions and tool-result guardrails.

**Why P2**: P1 covers the two calls that already exist (intent + model choice).

**Acceptance Criteria**:

1. WHEN P2 is scheduled THEN a canvas node and guardrail middleware SHALL consume `ClassifierInterface` without a new vendor SDK.

---

## Edge Cases

- WHEN intent descriptions are blank THEN the Choice option text SHALL fall back to the intent name, then the id.
- WHEN fewer than one routing tier is enabled THEN save SHALL reject the payload.
- WHEN `easy_max` is not below `medium_max` and both easy and medium tiers exist THEN save SHALL reject the payload.
- WHEN the classifier throws THEN the error SHALL propagate; the router fallback order is not a substitute for a failed judgment.
- WHEN engine is `jev` THEN provider, model, and vision SHALL be ignored at runtime.

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| --- | --- | --- | --- |
| JEV-01 | P1 intent engine | Execute | Verified |
| JEV-02 | P1 intent threshold + memory | Execute | Verified |
| JEV-03 | P1 classifier span on intent | Execute | Verified |
| JEV-04 | P1 agent routing | Execute | Verified |
| JEV-05 | P1 fail-closed key | Execute | Verified |
| JEV-06 | P1 export + codegen | Execute | Verified |
| JEV-07 | P2 generic node / guardrails | — | Pending |

**Coverage:** 7 total, 6 mapped to P1 tasks, 1 deferred.

---

## Success Criteria

- [ ] Existing intent and agent tests pass with engine/routing left off
- [ ] JEV intent and three-tier routing tests pass with `FakeClassifier` and no network
- [ ] `TYPESAFE_KEY` documented as opt-in
