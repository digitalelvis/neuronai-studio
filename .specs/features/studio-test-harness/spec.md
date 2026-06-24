# Studio Test Harness — Specification

## Overview

Reusable **StudioChat** + **StudioPlayground** for testing Agents and Workflows with conversational UX, multimodal attachments, and Human-in-the-loop via chat.

## Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| CHAT-01 | StudioChat renders user/assistant/system/tool/workflow message bubbles | P0 |
| CHAT-02 | Composer sends text; Enter sends, Shift+Enter newline | P0 |
| CHAT-03 | Assistant responses stream via SSE token events | P0 |
| CHAT-04 | Attachments: image, audio, video, document in composer | P1 |
| PG-01 | StudioPlayground exposes JSON context editor with validation | P1 |
| PG-02 | Presets saved/loaded per entity in localStorage | P1 |
| ADP-01 | AgentSessionAdapter → POST agent chat stream | P0 |
| ADP-02 | WorkflowSessionAdapter → POST workflow run stream + canvas events | P0 |
| ADP-03 | Resume via chat on `human_input_required` | P0 |
| WF-01 | Human node pauses run; chat reply resumes workflow | P0 |
| API-01 | POST stream replaces GET for workflow runs | P0 |
| API-02 | POST /studio/attachments with mime/size validation | P1 |

## Acceptance

- Agent Playground uses StudioChat with message history and streaming
- Workflow Editor Test tab uses same StudioChat + Playground
- Human node shows system prompt in chat; user reply continues run
- No modals for test input or human input
