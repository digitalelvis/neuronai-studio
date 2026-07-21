<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Exceptions;

use RuntimeException;

/**
 * Raised when a node inside a parallel branch requests a pause (Human node or
 * tool approval) so the workflow can checkpoint. It carries the parallel
 * context needed to resume only the interrupted branch while preserving the
 * results of branches that already completed.
 */
class ParallelBranchInterruptException extends RuntimeException
{
    public const REASON_HUMAN = 'human';

    public const REASON_TOOL_APPROVAL = 'tool_approval';

    /**
     * @param  string  $forkId  The fork node that spawned the branches.
     * @param  string  $joinId  The join node the branches converge into.
     * @param  string  $branchId  The interrupted branch id.
     * @param  string  $pendingNodeId  The node inside the branch awaiting input/approval.
     * @param  string  $outputKey  State key the awaited response is written to (human).
     * @param  string  $reason  Interrupt reason: {@see self::REASON_HUMAN} or {@see self::REASON_TOOL_APPROVAL}.
     * @param  array<string, mixed>  $pendingState  Isolated branch state at interrupt time.
     * @param  array<string, mixed>  $completedResults  Results of branches already finished.
     * @param  array<string, mixed>  $completedOutputs  Merged output keys of finished branches.
     * @param  list<array{name: string, arguments: array<string, mixed>, call_id: ?string}>  $pendingTools
     * @param  ?string  $serializedInterrupt  Serialized NeuronAI WorkflowInterrupt for tool-approval resume.
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
        public readonly array $pendingTools = [],
        public readonly ?string $serializedInterrupt = null,
    ) {
        parent::__construct($prompt);
    }

    public function isToolApproval(): bool
    {
        return $this->reason === self::REASON_TOOL_APPROVAL;
    }
}
