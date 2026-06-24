<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime\Exceptions;

use RuntimeException;

class HumanInputRequiredException extends RuntimeException
{
    public function __construct(
        public readonly string $nodeId,
        public readonly string $prompt,
        public readonly string $outputKey,
    ) {
        parent::__construct($prompt);
    }
}
