<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use NeuronAI\Workflow\WorkflowState;

class WorkflowStateValue
{
    public static function get(WorkflowState $state, string $key): mixed
    {
        return data_get($state->all(), $key);
    }
}
