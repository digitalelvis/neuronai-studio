<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Fixtures;

use NeuronAI\Workflow\WorkflowState;

class InvokeTestHook
{
    public function __invoke(WorkflowState $state): string
    {
        return (string) $state->get('input', '').'-hooked';
    }
}
