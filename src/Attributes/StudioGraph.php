<?php

namespace ElvisLopesDigital\NeuronAIStudio\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class StudioGraph
{
    /**
     * @param  array{version?: int, nodes?: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>, viewport?: array<string, float|int>}  $graph
     */
    public function __construct(
        public string $name,
        public string $description = '',
        public string $status = 'draft',
        public array $graph = [],
    ) {}
}
