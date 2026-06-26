# Flow Nodes

Flow nodes control workflow execution structure — entry points, termination, pauses, and timing.

## Start

**Purpose:** Entry point for every workflow. Exactly one Start node is required.

| Config | Description |
|--------|-------------|
| (none) | Passes through to the next connected node |

All runs begin at the Start node. Initial state is merged from the test harness message and optional "Initial state JSON".

## Stop

**Purpose:** Terminates the workflow run successfully.

| Config | Description |
|--------|-------------|
| (none) | Marks run as completed |

At least one Stop node is required. A workflow may have multiple Stop nodes for different exit paths.

## Delay

**Purpose:** Pause execution for a specified duration.

| Config | Description |
|--------|-------------|
| `seconds` | Delay duration (integer) |

Useful for rate limiting, waiting on external systems, or demo pacing.

```mermaid
flowchart LR
    Start[Start] --> Agent[Agent] --> Delay[Delay 5s] --> LLM[LLM summary] --> Stop[Stop]
```

## Human

**Purpose:** Pause execution and wait for user input (Human-in-the-Loop).

| Config | Description |
|--------|-------------|
| `prompt` | Message shown to the user |
| `output_key` | State key for the reply (default: `human_response`) |

When the Human node executes, the workflow pauses and saves a checkpoint. The user replies via the test harness, and execution resumes from the checkpoint.

See [Human-in-the-Loop](human-in-the-loop.md) for the full resume flow.

## Flow node summary

| Node | Category | Handles |
|------|----------|---------|
| Start | flow | 1 output |
| Stop | flow | 1 input |
| Delay | flow | 1 input, 1 output |
| Human | flow | 1 input, 1 output |

## Related code

- `StartNodeExecutor`, `StopNodeExecutor`, `DelayNodeExecutor`, `HumanNodeExecutor`

## See also

- [AI Nodes](ai-nodes.md)
- [Logic Nodes](logic-nodes.md)
