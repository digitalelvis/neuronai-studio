<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Fixtures;

use NeuronAI\Workflow\Events\StartEvent;
use NeuronAI\Workflow\Events\StopEvent;
use NeuronAI\Workflow\Node;
use NeuronAI\Workflow\WorkflowState;

class SampleInterruptNode extends Node
{
    public const STUDIO_NODE_ID = 'human_1';

    public function __invoke(StartEvent $event, WorkflowState $state): StopEvent
    {
        return new StopEvent($state->all());
    }
}
