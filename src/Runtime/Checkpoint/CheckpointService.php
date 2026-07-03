<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Checkpoint;

use DigitalElvis\NeuronAIStudio\Models\WorkflowCheckpoint;
use Illuminate\Support\Carbon;

/**
 * Persists per-node execution results so that resuming a workflow can skip the
 * re-execution of expensive nodes (rag, llm, agent, tool). Checkpoints are
 * scoped by `trace_id + node_id + iteration` and invalidated when the relevant
 * input state changes (input hash mismatch).
 */
class CheckpointService
{
    public function enabled(): bool
    {
        return (bool) config('neuronai-studio.checkpoints.enabled', true);
    }

    /**
     * Deterministic checkpoint key (mirrors the design:
     * sha256(trace_id | node_id | iteration | input_hash)).
     */
    public function key(int|string $traceId, string $nodeId, int $iteration, string $inputHash): string
    {
        return hash('sha256', implode('|', [$traceId, $nodeId, $iteration, $inputHash]));
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function hashInput(array $input): string
    {
        ksort($input);

        return hash('sha256', json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    public function lookup(int|string $traceId, string $nodeId, int $iteration): ?WorkflowCheckpoint
    {
        /** @var WorkflowCheckpoint|null $checkpoint */
        $checkpoint = WorkflowCheckpoint::query()
            ->where('workflow_trace_id', $traceId)
            ->where('node_id', $nodeId)
            ->where('iteration', $iteration)
            ->first();

        if ($checkpoint === null) {
            return null;
        }

        if ($checkpoint->isExpired()) {
            $checkpoint->delete();

            return null;
        }

        return $checkpoint;
    }

    /**
     * @param  array<string, mixed>  $statePayload
     */
    public function store(
        int|string $traceId,
        string $nodeId,
        int $iteration,
        string $inputHash,
        array $statePayload,
        string $handle,
    ): WorkflowCheckpoint {
        return WorkflowCheckpoint::query()->updateOrCreate(
            [
                'workflow_trace_id' => $traceId,
                'node_id' => $nodeId,
                'iteration' => $iteration,
            ],
            [
                'input_hash' => $inputHash,
                'state_payload' => $statePayload,
                'handle' => $handle,
                'expires_at' => $this->expiresAt(),
            ],
        );
    }

    public function forget(int|string $traceId): void
    {
        WorkflowCheckpoint::query()
            ->where('workflow_trace_id', $traceId)
            ->delete();
    }

    public function purgeExpired(): int
    {
        return WorkflowCheckpoint::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();
    }

    protected function expiresAt(): ?Carbon
    {
        $ttl = config('neuronai-studio.checkpoints.ttl');

        if ($ttl === null || (int) $ttl <= 0) {
            return null;
        }

        return now()->addMinutes((int) $ttl);
    }
}
