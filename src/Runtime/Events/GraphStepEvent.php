<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Events;

use NeuronAI\Workflow\Events\Event;

class GraphStepEvent implements Event
{
    public function __construct(
        public string $nodeId,
    ) {}
}
