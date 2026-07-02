<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

class CycleDetector
{
    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, array{source: string, target: string, sourceHandle: string}>
     */
    public function backEdges(array $nodes, array $edges): array
    {
        $nodeIds = $this->nodeIds($nodes);
        $adjacency = $this->adjacencyWithHandles($edges);
        $visited = [];
        $stack = [];
        $back = [];

        foreach (array_keys($nodeIds) as $nodeId) {
            if (! isset($visited[$nodeId])) {
                $this->dfs($nodeId, $adjacency, $visited, $stack, $back);
            }
        }

        return $back;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     */
    public function hasCycle(array $nodes, array $edges): bool
    {
        return $this->backEdges($nodes, $edges) !== [];
    }

    /**
     * @param  array<int, array{source: string, target: string, sourceHandle: string}>  $backEdges
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, array{source: string, target: string, sourceHandle: string}>
     */
    public function unauthorizedBackEdges(array $backEdges, array $nodes, array $edges): array
    {
        $loopNodes = $this->authorizedLoopNodes($nodes);

        if ($loopNodes === []) {
            return $backEdges;
        }

        return array_values(array_filter(
            $backEdges,
            function (array $edge) use ($loopNodes, $nodes, $edges) {
                $target = $edge['target'];

                foreach (array_keys($loopNodes) as $loopId) {
                    if ($target === $loopId) {
                        return false;
                    }

                    if ($this->isReachableFromLoopContinueHandle($loopId, $target, $nodes, $edges)) {
                        return false;
                    }
                }

                return true;
            },
        ));
    }

    public function isReachableFromLoopContinueHandle(
        string $loopId,
        string $targetId,
        array $nodes,
        array $edges,
    ): bool {
        $context = new GraphContext($nodes, $edges);
        $continueTarget = $context->targetForHandle($loopId, 'continue');

        if ($continueTarget === null) {
            return false;
        }

        if ($continueTarget === $targetId) {
            return true;
        }

        $adjacency = $this->adjacency($edges);
        $visited = [];
        $queue = [$continueTarget];

        while ($queue !== []) {
            $current = array_shift($queue);

            if ($current === $targetId) {
                return true;
            }

            if ($current === $loopId || isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            foreach ($adjacency[$current] ?? [] as $next) {
                if ($next !== $loopId) {
                    $queue[] = $next;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, array{max_steps: int}>
     */
    protected function authorizedLoopNodes(array $nodes): array
    {
        $loops = [];

        foreach ($nodes as $node) {
            if (($node['type'] ?? '') !== 'loop') {
                continue;
            }

            $id = (string) ($node['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $maxSteps = $this->resolveMaxSteps($node);

            if ($maxSteps <= 0) {
                continue;
            }

            $loops[$id] = ['max_steps' => $maxSteps];
        }

        return $loops;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, true>
     */
    protected function nodeIds(array $nodes): array
    {
        $ids = [];

        foreach ($nodes as $node) {
            $id = $node['id'] ?? null;

            if (is_string($id) && $id !== '') {
                $ids[$id] = true;
            }
        }

        return $ids;
    }

    /**
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<string, array<int, string>>
     */
    protected function adjacency(array $edges): array
    {
        $adjacency = [];

        foreach ($edges as $edge) {
            $source = $edge['source'] ?? null;
            $target = $edge['target'] ?? null;

            if (! is_string($source) || ! is_string($target)) {
                continue;
            }

            $adjacency[$source][] = $target;
        }

        return $adjacency;
    }

    /**
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<string, array<int, array{target: string, sourceHandle: string}>>
     */
    protected function adjacencyWithHandles(array $edges): array
    {
        $adjacency = [];

        foreach ($edges as $edge) {
            $source = $edge['source'] ?? null;
            $target = $edge['target'] ?? null;

            if (! is_string($source) || ! is_string($target)) {
                continue;
            }

            $adjacency[$source][] = [
                'target' => $target,
                'sourceHandle' => (string) ($edge['sourceHandle'] ?? 'default'),
            ];
        }

        return $adjacency;
    }

    /**
     * @param  array<string, array<int, array{target: string, sourceHandle: string}>>  $adjacency
     * @param  array<string, true>  $visited
     * @param  array<string, true>  $stack
     * @param  array<int, array{source: string, target: string, sourceHandle: string}>  $back
     */
    protected function dfs(
        string $nodeId,
        array $adjacency,
        array &$visited,
        array &$stack,
        array &$back,
    ): void {
        $visited[$nodeId] = true;
        $stack[$nodeId] = true;

        foreach ($adjacency[$nodeId] ?? [] as $edge) {
            $target = $edge['target'];

            if (! isset($visited[$target])) {
                $this->dfs($target, $adjacency, $visited, $stack, $back);
            } elseif (isset($stack[$target])) {
                $back[] = [
                    'source' => $nodeId,
                    'target' => $target,
                    'sourceHandle' => $edge['sourceHandle'],
                ];
            }
        }

        unset($stack[$nodeId]);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function resolveMaxSteps(array $node): int
    {
        $data = $node['data'] ?? [];

        if (isset($data['max_steps'])) {
            return max(0, (int) $data['max_steps']);
        }

        return max(0, (int) config('neuronai-studio.loop.default_max_steps', 10));
    }
}
