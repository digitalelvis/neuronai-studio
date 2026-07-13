<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use NeuronAI\Workflow\WorkflowState;

class BuilderWorkflowState extends WorkflowState
{
    /** @var null|(callable(string, array): void) */
    public $stepEmitter = null;

    public function __construct(
        public GraphContext $graphContext,
        public ?string $workflowRunId = null,
        array $data = [],
    ) {
        parent::__construct($data);
    }

    public function emitStep(string $event, array $data = []): void
    {
        if ($this->stepEmitter !== null) {
            ($this->stepEmitter)($event, $data);
        }
    }
}
