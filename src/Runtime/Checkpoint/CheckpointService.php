<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Checkpoint;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use Illuminate\Support\Carbon;

/**
 * DTO representing a cached node checkpoint, loaded from StudioRun checkpoint_state.
 */
class WorkflowCheckpoint
{
    public function __construct(
        public string $input_hash,
        public array $state_payload,
        public string $handle,
        public ?string $expires_at = null
    ) {}

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return now()->greaterThan(Carbon::parse($this->expires_at));
    }
}

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
     * Deterministic checkpoint key.
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
        $run = StudioRun::find($traceId);
        if ($run === null) {
            return null;
        }

        $checkpoints = $run->checkpoint_state['node_checkpoints'] ?? [];
        $key = "{$nodeId}_{$iteration}";

        if (! isset($checkpoints[$key])) {
            return null;
        }

        $data = $checkpoints[$key];
        $checkpoint = new WorkflowCheckpoint(
            $data['input_hash'] ?? '',
            $data['state_payload'] ?? [],
            $data['handle'] ?? 'default',
            $data['expires_at'] ?? null
        );

        if ($checkpoint->isExpired()) {
            unset($checkpoints[$key]);
            $state = $run->checkpoint_state ?? [];
            $state['node_checkpoints'] = $checkpoints;
            $run->update(['checkpoint_state' => $state]);

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
        $run = StudioRun::find($traceId);
        if ($run === null) {
            return new WorkflowCheckpoint($inputHash, $statePayload, $handle);
        }

        $state = $run->checkpoint_state ?? [];
        $checkpoints = $state['node_checkpoints'] ?? [];

        $expiresAt = $this->expiresAt();
        $checkpoints["{$nodeId}_{$iteration}"] = [
            'input_hash' => $inputHash,
            'state_payload' => $statePayload,
            'handle' => $handle,
            'expires_at' => $expiresAt ? $expiresAt->toIso8601String() : null,
        ];

        $state['node_checkpoints'] = $checkpoints;
        $run->update(['checkpoint_state' => $state]);

        return new WorkflowCheckpoint(
            $inputHash,
            $statePayload,
            $handle,
            $expiresAt ? $expiresAt->toIso8601String() : null
        );
    }

    public function forget(int|string $traceId): void
    {
        $run = StudioRun::find($traceId);
        if ($run !== null) {
            $state = $run->checkpoint_state ?? [];
            unset($state['node_checkpoints']);
            $run->update(['checkpoint_state' => $state]);
        }
    }

    public function purgeExpired(): int
    {
        return 0;
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
