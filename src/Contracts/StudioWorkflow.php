<?php

namespace DigitalElvis\NeuronAIStudio\Contracts;

interface StudioWorkflow
{
    /** @return array{name?: string, description?: string, status?: string} */
    public static function studioMeta(): array;

    /** @return array{version: int, nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>, viewport?: array<string, float|int>} */
    public static function studioGraph(): array;
}
