<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Exceptions;

use RuntimeException;

class MaxLoopIterationsException extends RuntimeException
{
    public function __construct(
        public readonly string $nodeId,
        public readonly int $iteration,
        public readonly int $maxSteps,
    ) {
        parent::__construct(
            "Max loop iterations exceeded at node {$nodeId} (iteration {$iteration}, max {$maxSteps}).",
        );
    }
}
