# Workflow Reply Contract — Specification

## Problem Statement

Workflow channels (Studio chat, Vercel/AG-UI integrate, WhatsApp, MCP, nested `run_workflow`) have no canonical user-facing reply. Each surface guesses from state (`finalText` last-string, Pretty diffs, full JSON). Large graphs with Human pauses, internal `stream: true`, and multiple Stop paths produce empty or wrong replies.

## Goals

- [ ] Stop nodes declare an explicit `reply` template written to `state.reply`
- [ ] One `WorkflowReplyResolver` serves all channel consumers
- [ ] Human pause publishes the interpolated prompt as channel text
- [ ] Token stream only reaches the channel when the node is reply-facing
- [ ] GraphValidator surfaces dead-ends and duplicate handles that cause silent empty runs

## Out of Scope

| Feature | Reason |
| ------- | ------ |
| Separate Chatflow vs Workflow app types | One graph; Stop + Human cover the case |
| Dedicated `answer` palette node (P2) | Stop.reply is enough for MVP |
| Send Message without pause (n8n style) | P2; Human + Stop cover current graphs |
| Authoring fix of customer production graphs | Feature provides tools + validator |

---

## User Stories

### P1: Stop declares reply ⭐ MVP

**User Story**: As a workflow author, I want each Stop to set `reply` from a template so every branch ends with a known user message.

**Acceptance Criteria**:

1. WHEN a Stop has `data.reply` THEN runtime SHALL interpolate it and write `state.reply`.
2. WHEN Stop has empty/missing `reply` THEN runtime SHALL leave `reply` unset (legacy fallback may apply).
3. WHEN GraphValidator runs and a Stop lacks `reply` THEN system SHALL emit a warning (not a hard error).

### P1: WorkflowReplyResolver ⭐ MVP

**User Story**: As a channel consumer, I want one resolver so I never invent last-string heuristics myself.

**Acceptance Criteria**:

1. WHEN run status is `awaiting_input` THEN resolver text SHALL be the Human prompt from the pause event/checkpoint when available.
2. WHEN `state.reply` is a non-empty string THEN resolver SHALL return it.
3. WHEN `state.reply` is a non-string THEN resolver SHALL stringify (JSON for arrays/objects).
4. WHEN no reply was declared THEN resolver MAY fall back to legacy last non-meta string (compat).

### P1: Human prompt on channel ⭐ MVP

**User Story**: As an integrate client, I want the Human prompt as assistant text when the workflow pauses.

**Acceptance Criteria**:

1. WHEN `human_input_required` fires THEN StreamBridge SHALL emit the prompt as TextChunk before/with awaiting signal when prompt is non-empty.
2. WHEN awaiting signal is emitted THEN payload SHALL include `prompt` when known.

### P1: Stream isolation ⭐ MVP

**User Story**: As an author, I want internal LLM/agent streams to fill `output_key` without becoming the WhatsApp reply unless marked reply-facing.

**Acceptance Criteria**:

1. WHEN node has `stream: true` and `publish_reply` is not true THEN Studio SSE MAY still emit `token` for timeline, but StreamBridge SHALL NOT forward those tokens to the wire protocol.
2. WHEN node has `publish_reply: true` (or is the only streaming reply path) THEN tokens SHALL forward to the channel.
3. Default for agent/llm: `publish_reply` defaults to `true` when unset for backward compat of simple chat graphs; file/internal nodes should set `publish_reply: false` or omit stream.

**Decision (MVP):** `publish_reply` defaults to **`true`** when absent (preserve existing single-agent chat). Authors set `publish_reply: false` on internal streamers. StreamBridge only forwards `token` when `data.publish_reply !== false` on the event payload.

### P1: Graph validation ⭐ MVP

**Acceptance Criteria**:

1. WHEN two+ control-flow edges share the same `source` + `sourceHandle` (non-fork) THEN validator SHALL error.
2. WHEN Human has no outgoing control-flow edge THEN validator SHALL warn.
3. WHEN condition has no `false` edge THEN validator SHALL warn.
4. WHEN loop has no `exit` edge THEN validator SHALL warn.
5. WHEN switch has no `default` edge THEN validator SHALL warn.

### P1: Nested / MCP / Pretty consumers ⭐ MVP

**Acceptance Criteria**:

1. WHEN `WorkflowAsTool` / `run_workflow` complete THEN default serialization SHALL prefer resolver text (`reply`), with opt-in `output_mode: state` for full snapshot JSON.
2. WHEN MCP returns workflow result THEN payload SHALL include `reply` from the resolver.
3. WHEN Studio Pretty has `output.reply` THEN it SHALL show that as the primary assistant content entry.

---

## Traceability

| ID | Requirement |
|----|-------------|
| WRC-01 | Stop.reply → state.reply |
| WRC-02 | WorkflowReplyResolver |
| WRC-03 | Human prompt on channel |
| WRC-04 | publish_reply stream gate |
| WRC-05 | Duplicate handle validation |
| WRC-06 | Dead-end warnings |
| WRC-07 | Nested/MCP/Pretty consumers |
