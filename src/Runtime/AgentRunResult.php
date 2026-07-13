<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

class AgentRunResult
{
    /**
     * @param  array<int, array{name: string, inputs: array<string, mixed>, result: string|null, type: string}>  $toolEvents
     * @param  array<string, mixed>|null  $structured
     */
    public function __construct(
        public readonly string $content = '',
        public readonly array $toolEvents = [],
        public readonly ?array $structured = null,
        public readonly ?string $runId = null,
    ) {}
}
