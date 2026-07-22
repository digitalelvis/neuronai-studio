# Logic Nodes

Logic nodes control workflow branching and state manipulation.

## Loop

**Purpose:** Repeat a subgraph until an exit condition is met or `max_steps` is reached.

| Config | Description |
|--------|-------------|
| `max_steps` | Maximum iterations before `MaxLoopIterationsException` (default from config) |
| `state_key` | Key to read for exit condition (default: `input`) |
| `operator` | Same operators as Condition (`not_empty`, `empty`, `equals`, `not_equals`, `contains`) |
| `value` | Comparison value for `equals` / `contains` operators |

The node has two output handles:

| Handle | When |
|--------|------|
| `continue` | Exit condition is **not** met — follow the loop body |
| `exit` | Exit condition **is** met — leave the loop |

```mermaid
flowchart LR
    Loop[Loop] -->|continue| Body[LLM / Agent]
    Body --> Loop
    Loop -->|exit| After[Condition / Stop]
```

Back-edges into the loop body require a Loop node with `max_steps` > 0. `GraphValidator` rejects unauthorized cycles.

## Condition

**Purpose:** Branch execution based on a workflow state value.

| Config | Description |
|--------|-------------|
| `state_key` | Key to read from state (default: `input`) |
| `operator` | Comparison operator |
| `value` | Comparison value (for equals/contains operators) |

| Operator | Behavior |
|----------|----------|
| `not_empty` | Value is non-empty → true branch |
| `empty` | Value is empty → true branch |
| `equals` | Loose equality (`==`) against value |
| `not_equals` | Not equal to value |
| `contains` | String contains value |

The node has two output handles: `true` and `false`. Connect each to different downstream nodes.

See [State & Conditions](../state-and-conditions.md) for detailed examples.

## Set State

**Purpose:** Write or copy values into workflow state.

| Config | Description |
|--------|-------------|
| `key` | Target state key |
| `value` | Static value to write |
| `from_key` | Copy value from another state key (alternative to `value`) |

Use Set State to:

- Initialize default values mid-flow
- Rename or duplicate state keys
- Set flags for downstream Condition nodes

```mermaid
flowchart TD
    Start[Start] --> SetTier["Set State: tier=gold"]
    SetTier --> Cond{Condition tier equals gold?}
    Cond -->|true| VIP[VIP Agent]
    Cond -->|false| Std[Standard Agent]
```

## Invoke

**Purpose:** Call an allowlisted host PHP hook from the graph and write the return value into workflow state.

| Config | Description |
|--------|-------------|
| `hook_class` | Fully qualified class name (FQCN) that implements `__invoke(WorkflowState): mixed` |
| `output_key` | State key for the return value (default: `invoke_result`) |

The host must list each FQCN under `neuronai-studio.invoke_hooks` (fail-closed: empty list allows nothing). Studio resolves the class from the Laravel container and calls `__invoke` with the current `WorkflowState`.

Use Invoke when you need a one-off host callback without registering a full custom node type. Prefer a [custom node type](../../../extending/custom-node-types.md) when you need a reusable palette entry, multiple handles, or rich inspector fields.

```php
// config/neuronai-studio.php
'invoke_hooks' => [
    \App\Neuron\Hooks\EnrichLead::class,
],

// app/Neuron/Hooks/EnrichLead.php
namespace App\Neuron\Hooks;

use NeuronAI\Workflow\WorkflowState;

class EnrichLead
{
    public function __invoke(WorkflowState $state): array
    {
        return ['tier' => 'gold', 'email' => $state->get('email')];
    }
}
```

```mermaid
flowchart LR
    Start[Start] --> Invoke[Invoke EnrichLead]
    Invoke --> Cond{Condition}
```

## Fork

**Purpose:** Run several branch subgraphs in parallel, then converge into a Join node.

| Config | Description |
|--------|-------------|
| `branches` | List of branch ids. Each id becomes a named output handle on the canvas |

Wiring convention:

| Edge | Handle | Meaning |
|------|--------|---------|
| `fork → join` | `default` | The main continuation (required — validation fails without it) |
| `fork → branch entry` | `<branchId>` | One edge per branch, using the branch id as the source handle |
| `branch tail → join` | `default` | Every branch must converge back into the same Join node |

The interpreted runtime executes branches with **isolated state per branch**
(mirroring NeuronAI's `ParallelEvent` merge semantics), so branches never see each other's
partial writes. Each branch result is keyed by its branch id. With
`parallel.concurrency=concurrent` (default) and Amp available, pending branches run as
concurrent Amp fibers; set `sequential` to force the legacy foreach order.

Human nodes and **tool approval** inside a branch are supported: the run pauses with a
`kind: parallel` checkpoint, completed siblings are preserved, and resume continues only
the pending branch (then runs any not-yet-started branches). See
[Human-in-the-Loop → Parallel branches](../human-in-the-loop.md#parallel-branches).

```mermaid
flowchart LR
    Fork[Fork] -->|branch_a| A[LLM / Agent]
    Fork -->|branch_b| B[LLM / Agent]
    A --> Join[Join]
    B --> Join
    Fork -->|default| Join
    Join --> Stop[Stop]
```

## Join

**Purpose:** Merge the results collected by the paired Fork node into a single state key.

| Config | Description |
|--------|-------------|
| `output_key` | State key that receives the merged map (default: `parallel_results`) |

The output is an object keyed by branch id, e.g. `{ branch_a: …, branch_b: … }`. A branch's
value is its single produced output when unambiguous, otherwise the full map of keys the
branch wrote.

See [Runtime & Traces](../runtime-and-traces.md#parallel-execution) for branch step events and
[Human-in-the-Loop](../human-in-the-loop.md#parallel-branches) for pausing inside a branch.

## Logic node summary

| Node | Inputs | Outputs |
|------|--------|---------|
| Loop | 1 | 2 (continue, exit) |
| Condition | 1 | 2 (true, false) |
| Set State | 1 | 1 |
| Fork | 1 | 1 (default) + 1 per branch |
| Join | N (branches) | 1 |

## Related code

- `LoopNodeExecutor`, `ConditionNodeExecutor`, `SetStateNodeExecutor`
- `ForkNodeExecutor`, `JoinNodeExecutor`, `ParallelBranchRunner`
- `StateTemplateInterpolator` — for `{{key}}` in other nodes, not Condition evaluation

## See also

- [State & Conditions](../state-and-conditions.md)
- [Flow Nodes](flow-nodes.md)
