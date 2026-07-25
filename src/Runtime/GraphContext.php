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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function incomingEdges(string $nodeId): array
    {
        return array_values(array_filter(
            $this->edges,
            fn (array $edge) => ($edge['target'] ?? null) === $nodeId
        ));
    }

    public function targetForHandle(string $nodeId, string $handle = 'default'): ?string
    {
        foreach ($this->outgoingEdges($nodeId) as $edge) {
            // Tool-binding pins are not control-flow edges.
            if (($edge['targetHandle'] ?? 'default') === 'tools') {
                continue;
            }

            $sourceHandle = $edge['sourceHandle'] ?? 'default';

            if ($sourceHandle === $handle) {
                return $edge['target'] ?? null;
            }
        }

        return null;
    }

    /**
     * Resolve tool/MCP bindings attached to an agent via targetHandle=tools edges.
     *
     * @return array<int, array{ref: string, only?: array<int, string>, config?: array<string, mixed>}>
     */
    public function toolBindingsFor(string $agentNodeId): array
    {
        $bindings = [];

        foreach ($this->incomingEdges($agentNodeId) as $edge) {
            if (($edge['targetHandle'] ?? 'default') !== 'tools') {
                continue;
            }

            $sourceId = (string) ($edge['source'] ?? '');
            if ($sourceId === '') {
                continue;
            }

            $source = $this->nodeConfig($sourceId);
            $type = (string) ($source['type'] ?? '');
            $data = is_array($source['data'] ?? null) ? $source['data'] : [];

            if ($type === 'tool') {
                $ref = (string) ($data['tool_ref'] ?? '');
                if ($ref === '') {
                    continue;
                }

                $binding = ['ref' => $ref];
                if (! empty($data['parameters']) && is_array($data['parameters'])) {
                    $binding['config'] = $data['parameters'];
                }
                $bindings[] = $binding;

                continue;
            }

            if ($type === 'mcp') {
                $slug = (string) ($data['mcp_server'] ?? '');
                if ($slug === '') {
                    continue;
                }

                $binding = ['ref' => "mcp:{$slug}"];
                $toolName = (string) ($data['tool_name'] ?? '');
                if ($toolName !== '') {
                    $binding['only'] = [$toolName];
                }
                $bindings[] = $binding;
            }
        }

        return $bindings;
    }
}
