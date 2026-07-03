<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\StructuredOutput;

use DigitalElvis\NeuronAIStudio\Registry\OutputClassRegistry;
use InvalidArgumentException;

class StructuredOutputResolver
{
    public function __construct(
        protected OutputClassRegistry $registry,
    ) {}

    public function resolve(string $reference): string
    {
        $reference = trim($reference);

        if ($reference === '') {
            throw new InvalidArgumentException('Structured output class reference cannot be empty.');
        }

        if ($this->registry->isValidOutputClass($reference)) {
            return $reference;
        }

        $resolved = $this->registry->findByShortName($reference);

        if ($resolved !== null) {
            return $resolved;
        }

        throw new InvalidArgumentException("Structured output class [{$reference}] not found or invalid.");
    }
}
