<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

class AgentRunResult
{
    /**
     * @param  array<int, array{name: string, inputs: array<string, mixed>, result: string|null, type: string}>  $toolEvents
     */
    public function __construct(
        public readonly string $content,
        public readonly array $toolEvents = [],
    ) {}
}
