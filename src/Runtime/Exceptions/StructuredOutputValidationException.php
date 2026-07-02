<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Exceptions;

use NeuronAI\Exceptions\AgentException;
use RuntimeException;
use Throwable;

class StructuredOutputValidationException extends RuntimeException
{
    /**
     * @param  array<int, string>  $validationErrors
     */
    public function __construct(
        string $message,
        public readonly array $validationErrors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromAgentException(AgentException $exception): self
    {
        $errors = array_values(array_filter(
            array_map(
                static fn (string $line): string => ltrim(trim($line), '- '),
                explode("\n", $exception->getMessage()),
            ),
            static fn (string $line): bool => $line !== '',
        ));

        return new self($exception->getMessage(), $errors, $exception);
    }
}
