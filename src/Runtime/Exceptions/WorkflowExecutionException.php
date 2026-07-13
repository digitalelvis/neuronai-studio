<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Exceptions;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use RuntimeException;
use Throwable;

class WorkflowExecutionException extends RuntimeException
{
    public function __construct(
        public readonly StudioRun $run,
        Throwable $previous,
    ) {
        parent::__construct($previous->getMessage(), 0, $previous);
    }
}
