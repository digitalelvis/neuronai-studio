<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Exceptions;

use RuntimeException;

/**
 * Raised when a node inside a parallel branch requests human input (Human node)
 * so the workflow can pause. It carries the parallel context needed to resume
 * only the interrupted branch while preserving the results of branches that
 * already completed.
 */
class ParallelBranchInterruptException extends RuntimeException
{
    /**
     * @param  string  $forkId  The fork node that spawned the branches.
     * @param  string  $joinId  The join node the branches converge into.
     * @param  string  $branchId  The interrupted branch id.
     * @param  string  $pendingNodeId  The node inside the branch awaiting input.
     * @param  string  $outputKey  State key the awaited response is written to.
     * @param  string  $reason  Interrupt reason (e.g. "human").
     * @param  array<string, mixed>  $pendingState  Isolated branch state at interrupt time.
     * @param  array<string, mixed>  $completedResults  Results of branches already finished.
     * @param  array<string, mixed>  $completedOutputs  Merged output keys of finished branches.
     */
    public function __construct(
        public readonly string $forkId,
        public readonly string $joinId,
        public readonly string $branchId,
        public readonly string $pendingNodeId,
        public readonly string $outputKey,
        public readonly string $prompt,
        public readonly string $reason,
        public readonly array $pendingState,
        public readonly array $completedResults,
        public readonly array $completedOutputs,
    ) {
        parent::__construct($prompt);
    }
}
