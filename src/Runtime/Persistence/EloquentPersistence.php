<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Persistence;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use NeuronAI\Exceptions\WorkflowException;
use NeuronAI\Workflow\Interrupt\WorkflowInterrupt;
use NeuronAI\Workflow\Persistence\PersistenceInterface;
use NeuronAI\Workflow\Persistence\SerializablePersistenceInterface;

/**
 * Database-backed persistence for native NeuronAI workflows. Serialized
 * interrupts are stored in the run's `checkpoint_state` column keyed by the
 * run ID (passed as $workflowId), giving durable resume across requests
 * and queue workers.
 */
class EloquentPersistence implements PersistenceInterface, SerializablePersistenceInterface
{
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
        $run = StudioRun::find($workflowId);
        if ($run === null) {
            return;
        }

        $state = $run->checkpoint_state ?? [];
        $state['interrupt'] = base64_encode($this->serialize($interrupt));

        $run->update(['checkpoint_state' => $state]);
    }

    public function load(string $workflowId): WorkflowInterrupt
    {
        $run = StudioRun::find($workflowId);
        if ($run === null) {
            throw new WorkflowException("No saved workflow found for ID: {$workflowId}.");
        }

        $encoded = $run->checkpoint_state['interrupt'] ?? null;

        if (! is_string($encoded)) {
            throw new WorkflowException("No saved workflow found for ID: {$workflowId}.");
        }

        return $this->unserialize((string) base64_decode($encoded));
    }

    public function delete(string $workflowId): void
    {
        $run = StudioRun::find($workflowId);
        if ($run !== null) {
            $state = $run->checkpoint_state ?? [];
            unset($state['interrupt']);
            $run->update(['checkpoint_state' => $state]);
        }
    }
}
