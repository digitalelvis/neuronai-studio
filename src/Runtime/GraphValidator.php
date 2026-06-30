<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use DigitalElvis\NeuronAIStudio\Registry\NodeTypeRegistry;
use InvalidArgumentException;

class GraphValidator
{
    public function __construct(
        protected NodeTypeRegistry $nodeTypes,
    ) {}

    /** @return array{valid: bool, errors: array<string>} */
    public function validate(array $graph): array
    {
        $errors = [];
        $nodes = $graph['nodes'] ?? [];
        $edges = $graph['edges'] ?? [];

        if (empty($nodes)) {
            return ['valid' => false, 'errors' => ['Graph must contain at least one node.']];
        }

        $startNodes = array_filter($nodes, fn ($n) => ($n['type'] ?? '') === 'start');
        $stopNodes = array_filter($nodes, fn ($n) => ($n['type'] ?? '') === 'stop');

        if (count($startNodes) !== 1) {
            $errors[] = 'Graph must contain exactly one start node.';
        }

        if (count($stopNodes) < 1) {
            $errors[] = 'Graph must contain at least one stop node.';
        }

        $nodeIds = [];
        foreach ($nodes as $node) {
            $id = $node['id'] ?? null;
            $type = $node['type'] ?? null;

            if (! $id) {
                $errors[] = 'All nodes must have an id.';
                continue;
            }

            if (isset($nodeIds[$id])) {
                $errors[] = "Duplicate node id: {$id}";
            }
            $nodeIds[$id] = true;

            if (! $type) {
                $errors[] = "Node {$id} is missing a type.";
                continue;
            }

            if (! $this->nodeTypes->has($type)) {
                $errors[] = "Unknown node type '{$type}' on node {$id}.";
            }
        }

        foreach ($edges as $edge) {
            $source = $edge['source'] ?? null;
            $target = $edge['target'] ?? null;

            if (! $source || ! $target) {
                $errors[] = 'All edges must have source and target.';
                continue;
            }

            if (! isset($nodeIds[$source])) {
                $errors[] = "Edge references unknown source node: {$source}";
            }

            if (! isset($nodeIds[$target])) {
                $errors[] = "Edge references unknown target node: {$target}";
            }
        }

        if (empty($errors) && ! empty($startNodes)) {
            $startId = array_values($startNodes)[0]['id'];
            if (! $this->canReachStop($startId, $nodes, $edges)) {
                $errors[] = 'No path from start to stop node exists.';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    protected function canReachStop(string $startId, array $nodes, array $edges): bool
    {
        $stopIds = collect($nodes)
            ->filter(fn ($n) => ($n['type'] ?? '') === 'stop')
            ->pluck('id')
            ->all();

        $adjacency = [];
        foreach ($edges as $edge) {
            $adjacency[$edge['source']][] = $edge['target'];
        }

        $visited = [];
        $queue = [$startId];

        while ($queue) {
            $current = array_shift($queue);

            if (in_array($current, $stopIds, true)) {
                return true;
            }

            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            foreach ($adjacency[$current] ?? [] as $next) {
                $queue[] = $next;
            }
        }

        return false;
    }

    public function assertValid(array $graph): void
    {
        $result = $this->validate($graph);

        if (! $result['valid']) {
            throw new InvalidArgumentException(implode(' ', $result['errors']));
        }
    }
}
