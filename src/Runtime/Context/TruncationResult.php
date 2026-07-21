<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Context;

final class TruncationResult
{
    public function __construct(
        public readonly string $text,
        public readonly bool $truncated,
        public readonly int $tokensBefore,
        public readonly int $tokensAfter,
        public readonly string $strategy,
    ) {}
}
