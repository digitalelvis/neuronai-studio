<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

class GraphContext
{
    /** @param array<string, mixed> $nodes */
    public function __construct(
        public array $nodes,
        public array $edges,
    ) {}

    public function nodeConfig(string $nodeId): array
    {
        foreach ($this->nodes as $node) {
            if (($node['id'] ?? null) === $nodeId) {
                return $node;
            }
        }

        return [];
    }

    public function outgoingEdges(string $nodeId): array
    {
        return array_values(array_filter(
            $this->edges,
            fn (array $edge) => ($edge['source'] ?? null) === $nodeId
        ));
    }

    public function targetForHandle(string $nodeId, string $handle = 'default'): ?string
    {
        foreach ($this->outgoingEdges($nodeId) as $edge) {
            $sourceHandle = $edge['sourceHandle'] ?? 'default';

            if ($sourceHandle === $handle) {
                return $edge['target'] ?? null;
            }
        }

        return null;
    }
}
