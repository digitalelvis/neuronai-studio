<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Persistence;

use DigitalElvis\NeuronAIStudio\Models\WorkflowCheckpoint;
use NeuronAI\Exceptions\WorkflowException;
use NeuronAI\Workflow\Interrupt\WorkflowInterrupt;
use NeuronAI\Workflow\Persistence\PersistenceInterface;
use NeuronAI\Workflow\Persistence\SerializablePersistenceInterface;

/**
 * Database-backed persistence for native NeuronAI workflows. Serialized
 * interrupts are stored in the shared `workflow_checkpoints` table keyed by the
 * workflow resume token (`workflow_key`), giving durable resume across requests
 * and queue workers without the volatility of `InMemoryPersistence`.
 */
class EloquentPersistence implements PersistenceInterface, SerializablePersistenceInterface
{
    protected const NODE_ID = '__native_interrupt';

    public function serialize(WorkflowInterrupt $interrupt): string
    {
        return serialize($interrupt);
    }

    public function unserialize(string $data): WorkflowInterrupt
    {
        return unserialize($data);
    }

    public function save(string $workflowId, WorkflowInterrupt $interrupt): void
    {
        WorkflowCheckpoint::query()->updateOrCreate(
            [
                'workflow_key' => $workflowId,
                'node_id' => self::NODE_ID,
                'iteration' => 0,
            ],
            [
                'workflow_trace_id' => null,
                'input_hash' => null,
                'state_payload' => ['interrupt' => base64_encode($this->serialize($interrupt))],
                'handle' => null,
                'expires_at' => null,
            ],
        );
    }

    public function load(string $workflowId): WorkflowInterrupt
    {
        /** @var WorkflowCheckpoint|null $checkpoint */
        $checkpoint = WorkflowCheckpoint::query()
            ->where('workflow_key', $workflowId)
            ->where('node_id', self::NODE_ID)
            ->first();

        $encoded = $checkpoint?->state_payload['interrupt'] ?? null;

        if (! is_string($encoded)) {
            throw new WorkflowException("No saved workflow found for ID: {$workflowId}.");
        }

        return $this->unserialize((string) base64_decode($encoded));
    }

    public function delete(string $workflowId): void
    {
        WorkflowCheckpoint::query()
            ->where('workflow_key', $workflowId)
            ->where('node_id', self::NODE_ID)
            ->delete();
    }
}
