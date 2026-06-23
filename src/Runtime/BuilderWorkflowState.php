<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime;

use NeuronAI\Workflow\WorkflowState;

class BuilderWorkflowState extends WorkflowState
{
    public function __construct(
        public GraphContext $graphContext,
        public ?int $workflowRunId = null,
        array $data = [],
    ) {
        parent::__construct($data);
    }
}
