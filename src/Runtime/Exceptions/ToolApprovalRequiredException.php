<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Exceptions;

use RuntimeException;

class ToolApprovalRequiredException extends RuntimeException
{
    /**
     * @param  list<array{name: string, arguments: array<string, mixed>, call_id: ?string}>  $pendingTools
     * @param  ?string  $serializedInterrupt  Serialized NeuronAI WorkflowInterrupt used to resume the agent after approval.
     */
    public function __construct(
        public readonly string $nodeId,
        public readonly array $pendingTools,
        public readonly string $approvalMessage,
        public readonly ?string $serializedInterrupt = null,
    ) {
        parent::__construct($approvalMessage);
    }
}
