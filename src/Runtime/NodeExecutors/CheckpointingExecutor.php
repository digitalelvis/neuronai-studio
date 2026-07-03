<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\Checkpoint\CheckpointService;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use NeuronAI\Workflow\WorkflowState;

/**
 * Decorates an inner node executor with checkpoint caching. When a node opts in
 * via `data.checkpoint: true` (and checkpoints are enabled globally), the result
 * of the first execution is persisted and reused on resume so the underlying
 * provider / retrieval is not called again.
 *
 * Nodes without the flag (or when disabled) delegate straight to the inner
 * executor with zero overhead, preserving existing behaviour.
 */
class CheckpointingExecutor implements NodeExecutorInterface
{
    /** @var list<string> Volatile keys excluded from input hashing and diffing. */
    protected array $volatileKeys = ['__steps', '__current_node_id', '__loop_iterations'];

    public function __construct(
        protected NodeExecutorInterface $inner,
        protected CheckpointService $checkpoints,
    ) {}

    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = $nodeConfig['data'] ?? [];
        $nodeId = (string) ($nodeConfig['id'] ?? '');

        if (! $this->shouldCheckpoint($data, $state, $nodeId)) {
            return $this->inner->execute($nodeConfig, $state, $context);
        }

        $traceId = $this->resolveTraceId($state);
        $iteration = $this->resolveIteration($state);
        $inputHash = $this->checkpoints->hashInput($this->filter($state->all()));

        $existing = $this->checkpoints->lookup($traceId, $nodeId, $iteration);

        if ($existing !== null && $existing->input_hash === $inputHash) {
            foreach ((array) $existing->state_payload as $key => $value) {
                $state->set((string) $key, $value);
            }

            if ($state instanceof BuilderWorkflowState) {
                $state->emitStep('checkpoint_hit', [
                    'node_id' => $nodeId,
                    'iteration' => $iteration,
                ]);
            }

            return (string) ($existing->handle ?? 'default');
        }

        $before = $this->filter($state->all());
        $handle = $this->inner->execute($nodeConfig, $state, $context);
        $after = $this->filter($state->all());

        $this->checkpoints->store($traceId, $nodeId, $iteration, $inputHash, $this->diff($before, $after), $handle);

        return $handle;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function shouldCheckpoint(array $data, WorkflowState $state, string $nodeId): bool
    {
        return ($data['checkpoint'] ?? false) === true
            && $nodeId !== ''
            && $this->checkpoints->enabled()
            && $this->resolveTraceId($state) !== null;
    }

    protected function resolveTraceId(WorkflowState $state): int|string|null
    {
        $traceId = $state->get('__workflow_trace_id');

        if ($traceId === null && $state instanceof BuilderWorkflowState) {
            $traceId = $state->workflowRunId;
        }

        return is_int($traceId) || (is_string($traceId) && $traceId !== '') ? $traceId : null;
    }

    protected function resolveIteration(WorkflowState $state): int
    {
        $loops = $state->get('__loop_iterations', []);

        if (! is_array($loops) || $loops === []) {
            return 0;
        }

        return (int) array_sum(array_map('intval', $loops));
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    protected function filter(array $state): array
    {
        foreach ($this->volatileKeys as $key) {
            unset($state[$key]);
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, mixed>
     */
    protected function diff(array $before, array $after): array
    {
        $changed = [];

        foreach ($after as $key => $value) {
            if (! array_key_exists($key, $before) || $before[$key] !== $value) {
                $changed[$key] = $value;
            }
        }

        return $changed;
    }
}
