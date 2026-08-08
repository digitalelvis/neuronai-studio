<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Exceptions;

use RuntimeException;

class ThreadOwnerMismatchException extends RuntimeException
{
    public function __construct(
        public readonly string $threadId,
        public readonly string $existingType,
        public readonly string $existingId,
        public readonly string $attemptedType,
        public readonly string $attemptedId,
    ) {
        parent::__construct(sprintf(
            'Thread "%s" is owned by %s:%s and cannot be rebound to %s:%s.',
            $threadId,
            $existingType,
            $existingId,
            $attemptedType,
            $attemptedId,
        ));
    }
}
