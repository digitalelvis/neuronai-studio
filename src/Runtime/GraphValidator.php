<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use DigitalElvis\NeuronAIStudio\Registry\NodeTypeRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\InvokeNodeExecutor;
use InvalidArgumentException;

class GraphValidator
{
    public function __construct(
        protected NodeTypeRegistry $nodeTypes,
        protected CycleDetector $cycleDetector,
    ) {}

    /** @return array{valid: bool, errors: array<string>} */
    public function validate(array $graph): array
    {
        $errors = [];
        $nodes = array_values(array_filter(
            $graph['nodes'] ?? [],
            fn ($n) => ($n['type'] ?? '') !== 'note',
        ));
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

        $controlEdges = $this->controlFlowEdges($edges, $nodes);
        $errors = array_merge($errors, $this->validateCycles($nodes, $controlEdges));
        $errors = array_merge($errors, $this->validateParallel($nodes, $controlEdges));
        $errors = array_merge($errors, $this->validateInvokeNodes($nodes));
        $errors = array_merge($errors, $this->validateAgentNodes($nodes));
        $errors = array_merge($errors, $this->validateToolModeNodes($nodes));
        $errors = array_merge($errors, $this->validateToolModeControlFlow($nodes, $edges));
        $errors = array_merge($errors, $this->validateToolBindingEdges($nodes, $edges));
        $errors = array_merge($errors, $this->validateToolsetSlugs($nodes, $edges));

        if (empty($errors) && ! empty($startNodes)) {
            $startId = array_values($startNodes)[0]['id'];
            if (! $this->canReachStop($startId, $nodes, $controlEdges)) {
                $errors[] = 'No path from start to stop node exists.';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Edges that participate in workflow control flow (excludes tool-binding pins
     * and Tool Mode nodes, which are binding-only).
     *
     * @param  array<int, array<string, mixed>>  $edges
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    protected function controlFlowEdges(array $edges, array $nodes = []): array
    {
        $toolModeIds = $this->toolModeNodeIds($nodes);

        return array_values(array_filter(
            $edges,
            function (array $edge) use ($toolModeIds): bool {
                if (($edge['targetHandle'] ?? 'default') === 'tools') {
                    return false;
                }

                if (($edge['sourceHandle'] ?? 'default') === 'toolset') {
                    return false;
                }

                $source = (string) ($edge['source'] ?? '');
                $target = (string) ($edge['target'] ?? '');

                if (isset($toolModeIds[$source]) || isset($toolModeIds[$target])) {
                    return false;
                }

                return true;
            },
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, true>
     */
    protected function toolModeNodeIds(array $nodes): array
    {
        $ids = [];

        foreach ($nodes as $node) {
            $id = (string) ($node['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $data = is_array($node['data'] ?? null) ? $node['data'] : [];
            if ($this->isToolModeEnabled($data)) {
                $ids[$id] = true;
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function isToolModeEnabled(array $data): bool
    {
        return filter_var($data['tool_mode'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, string>
     */
    protected function validateToolModeNodes(array $nodes): array
    {
        $errors = [];

        foreach ($nodes as $node) {
            $id = (string) ($node['id'] ?? 'unknown');
            $type = (string) ($node['type'] ?? '');
            $data = is_array($node['data'] ?? null) ? $node['data'] : [];

            if (! $this->isToolModeEnabled($data)) {
                continue;
            }

            $meta = $this->nodeTypes->has($type) ? $this->nodeTypes->meta($type) : [];
            if (! ($meta['toolable'] ?? false)) {
                $errors[] = "Node type '{$type}' is not toolable (node {$id}).";
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, string>
     */
    protected function validateToolModeControlFlow(array $nodes, array $edges): array
    {
        $errors = [];
        $toolModeIds = $this->toolModeNodeIds($nodes);

        if ($toolModeIds === []) {
            return [];
        }

        foreach ($edges as $edge) {
            if ($this->isToolBindingEdge($edge)) {
                continue;
            }

            $source = (string) ($edge['source'] ?? '');
            $target = (string) ($edge['target'] ?? '');

            if (isset($toolModeIds[$source]) || isset($toolModeIds[$target])) {
                $errors[] = "Tool Mode node cannot participate in control-flow edges ({$source} → {$target}).";
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $edge
     */
    protected function isToolBindingEdge(array $edge): bool
    {
        return ($edge['targetHandle'] ?? 'default') === 'tools'
            || ($edge['sourceHandle'] ?? 'default') === 'toolset';
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, string>
     */
    protected function validateToolsetSlugs(array $nodes, array $edges): array
    {
        $errors = [];
        $dataById = [];

        foreach ($nodes as $node) {
            $id = (string) ($node['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $dataById[$id] = is_array($node['data'] ?? null) ? $node['data'] : [];
        }

        /** @var array<string, array<string, string>> $slugsBySupervisor */
        $slugsBySupervisor = [];

        foreach ($edges as $edge) {
            if (($edge['targetHandle'] ?? 'default') !== 'tools') {
                continue;
            }

            if (($edge['sourceHandle'] ?? 'default') !== 'toolset') {
                continue;
            }

            $source = (string) ($edge['source'] ?? '');
            $target = (string) ($edge['target'] ?? '');
            if ($source === '' || $target === '') {
                continue;
            }

            $slug = $this->resolveToolExposureSlug($dataById[$source] ?? []);
            $existing = $slugsBySupervisor[$target][$slug] ?? null;

            if ($existing !== null && $existing !== $source) {
                $errors[] = "Duplicate toolset slug '{$slug}' on supervisor {$target} (nodes {$existing} and {$source}).";

                continue;
            }

            $slugsBySupervisor[$target][$slug] = $source;
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveToolExposureSlug(array $data): string
    {
        $exposure = is_array($data['tool_exposure'] ?? null) ? $data['tool_exposure'] : [];
        $slug = trim((string) ($exposure['slug'] ?? ''));

        if ($slug !== '') {
            return $slug;
        }

        return (string) (
            config('neuronai-studio.node_types.agent.tool_exposure.slug_prefix')
            ?: 'call_agent'
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, string>
     */
    protected function validateAgentNodes(array $nodes): array
    {
        $errors = [];

        foreach ($nodes as $node) {
            if (($node['type'] ?? '') !== 'agent') {
                continue;
            }

            $id = (string) ($node['id'] ?? 'unknown');
            $data = is_array($node['data'] ?? null) ? $node['data'] : [];
            $mode = $this->resolveAgentConfigMode($data);

            if ($mode === 'existing') {
                if (! isset($data['agent_id']) || $data['agent_id'] === '' || $data['agent_id'] === null) {
                    $errors[] = "Agent node {$id} in existing mode requires data.agent_id.";
                }

                continue;
            }

            $provider = trim((string) ($data['provider'] ?? ''));
            $model = trim((string) ($data['model'] ?? ''));

            if ($provider === '' || $model === '') {
                $errors[] = "Agent node {$id} in inline mode requires data.provider and data.model.";
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, string>
     */
    protected function validateToolBindingEdges(array $nodes, array $edges): array
    {
        $errors = [];
        $typeById = [];
        $dataById = [];

        foreach ($nodes as $node) {
            $id = (string) ($node['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $typeById[$id] = (string) ($node['type'] ?? '');
            $dataById[$id] = is_array($node['data'] ?? null) ? $node['data'] : [];
        }

        foreach ($edges as $edge) {
            if (($edge['targetHandle'] ?? 'default') !== 'tools') {
                continue;
            }

            $source = (string) ($edge['source'] ?? '');
            $target = (string) ($edge['target'] ?? '');
            $sourceHandle = (string) ($edge['sourceHandle'] ?? 'default');
            $sourceType = $typeById[$source] ?? '';
            $targetType = $typeById[$target] ?? '';

            if ($targetType !== 'agent') {
                $errors[] = "Tools edge target must be an agent node (got {$target}).";

                continue;
            }

            if ($sourceType === 'agent' && $sourceHandle === 'toolset') {
                if (! $this->isToolModeEnabled($dataById[$source] ?? [])) {
                    $errors[] = "Toolset edge source must have tool_mode enabled ({$source}).";
                }

                continue;
            }

            if (! in_array($sourceType, ['tool', 'mcp'], true)) {
                $errors[] = "Tools edge source must be a tool or mcp node (got {$sourceType} on {$source}).";
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveAgentConfigMode(array $data): string
    {
        $mode = (string) ($data['config_mode'] ?? '');

        if ($mode === 'inline' || $mode === 'existing') {
            return $mode;
        }

        return isset($data['agent_id']) && $data['agent_id'] !== '' && $data['agent_id'] !== null
            ? 'existing'
            : 'inline';
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, string>
     */
    protected function validateCycles(array $nodes, array $edges): array
    {
        if (! $this->cycleDetector->hasCycle($nodes, $edges)) {
            return [];
        }

        $errors = [];
        $backEdges = $this->cycleDetector->backEdges($nodes, $edges);
        $loopNodes = array_filter($nodes, fn ($n) => ($n['type'] ?? '') === 'loop');

        if ($loopNodes === []) {
            return ['Cyclic graph requires a loop node with max_steps.'];
        }

        $authorizedLoops = array_filter($loopNodes, function (array $node) {
            $data = $node['data'] ?? [];

            if (isset($data['max_steps'])) {
                return (int) $data['max_steps'] > 0;
            }

            return (int) config('neuronai-studio.loop.default_max_steps', 10) > 0;
        });

        if ($authorizedLoops === []) {
            $errors[] = 'Loop nodes in a cyclic graph must declare max_steps greater than 0.';
        }

        $unauthorized = $this->cycleDetector->unauthorizedBackEdges($backEdges, $nodes, $edges);

        if ($unauthorized !== []) {
            $errors[] = 'Cyclic graph requires a loop node with max_steps covering all back-edges.';
        }

        return $errors;
    }

    /**
     * Validates fork/join pairing (PE-04):
     *  - a fork must reach a join via its default handle,
     *  - a fork must declare at least one branch edge (non-default handle),
     *  - a join must be referenced by at least one fork.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, string>
     */
    protected function validateParallel(array $nodes, array $edges): array
    {
        $errors = [];

        $typeById = [];
        foreach ($nodes as $node) {
            $id = (string) ($node['id'] ?? '');
            if ($id !== '') {
                $typeById[$id] = (string) ($node['type'] ?? '');
            }
        }

        $forkIds = array_keys(array_filter($typeById, fn (string $type) => $type === 'fork'));
        $joinIds = array_keys(array_filter($typeById, fn (string $type) => $type === 'join'));

        if ($forkIds === [] && $joinIds === []) {
            return [];
        }

        $pairedJoins = [];

        foreach ($forkIds as $forkId) {
            $defaultTarget = null;
            $branchCount = 0;

            foreach ($edges as $edge) {
                if (($edge['source'] ?? null) !== $forkId) {
                    continue;
                }

                $handle = (string) ($edge['sourceHandle'] ?? 'default');

                if ($handle === 'default') {
                    $defaultTarget = $edge['target'] ?? null;
                } else {
                    $branchCount++;
                }
            }

            $joinFromData = ($this->nodeData($nodes, $forkId)['join'] ?? null) ?: null;
            $joinTarget = is_string($defaultTarget) && $defaultTarget !== '' ? $defaultTarget : $joinFromData;

            if (! is_string($joinTarget) || ($typeById[$joinTarget] ?? '') !== 'join') {
                $errors[] = "Fork node {$forkId} must connect to a join node via the default handle.";
            } else {
                $pairedJoins[$joinTarget] = true;
            }

            if ($branchCount < 1) {
                $errors[] = "Fork node {$forkId} must declare at least one branch edge.";
            }
        }

        foreach ($joinIds as $joinId) {
            if (! isset($pairedJoins[$joinId])) {
                $errors[] = "Join node {$joinId} has no paired fork node.";
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, mixed>
     */
    protected function nodeData(array $nodes, string $nodeId): array
    {
        foreach ($nodes as $node) {
            if ((string) ($node['id'] ?? '') === $nodeId) {
                return is_array($node['data'] ?? null) ? $node['data'] : [];
            }
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, string>
     */
    protected function validateInvokeNodes(array $nodes): array
    {
        $errors = [];
        $executor = new InvokeNodeExecutor;

        foreach ($nodes as $node) {
            if (($node['type'] ?? '') !== 'invoke') {
                continue;
            }

            $id = (string) ($node['id'] ?? 'unknown');
            $data = is_array($node['data'] ?? null) ? $node['data'] : [];
            $hookClass = is_string($data['hook_class'] ?? null) ? ltrim(trim($data['hook_class']), '\\') : '';

            if ($hookClass === '') {
                $errors[] = "Invoke node {$id} requires data.hook_class (FQCN).";

                continue;
            }

            if (! $executor->isAllowlisted($hookClass)) {
                $errors[] = "Invoke node {$id}: hook [{$hookClass}] is not in config('neuronai-studio.invoke_hooks').";

                continue;
            }

            if (! class_exists($hookClass)) {
                $errors[] = "Invoke node {$id}: hook class [{$hookClass}] does not exist.";

                continue;
            }

            if (! method_exists($hookClass, '__invoke')) {
                $errors[] = "Invoke node {$id}: hook [{$hookClass}] must implement __invoke().";
            }
        }

        return $errors;
    }

    protected function canReachStop(string $startId, array $nodes, array $edges): bool
    {
        $stopIds = collect($nodes)
            ->filter(fn ($n) => ($n['type'] ?? '') === 'stop')
            ->pluck('id')
            ->all();

        $loopLimits = $this->loopVisitLimits($nodes);
        $adjacency = [];
        foreach ($edges as $edge) {
            $adjacency[$edge['source']][] = $edge['target'];
        }

        $visitCounts = [];
        $queue = [$startId];

        while ($queue) {
            $current = array_shift($queue);

            if (in_array($current, $stopIds, true)) {
                return true;
            }

            $limit = $loopLimits[$current] ?? 1;
            $visitCounts[$current] = ($visitCounts[$current] ?? 0) + 1;

            if ($visitCounts[$current] > $limit) {
                continue;
            }

            foreach ($adjacency[$current] ?? [] as $next) {
                $queue[] = $next;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, int>
     */
    protected function loopVisitLimits(array $nodes): array
    {
        $limits = [];

        foreach ($nodes as $node) {
            if (($node['type'] ?? '') !== 'loop') {
                continue;
            }

            $id = (string) ($node['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $data = $node['data'] ?? [];
            $maxSteps = isset($data['max_steps'])
                ? max(1, (int) $data['max_steps'])
                : max(1, (int) config('neuronai-studio.loop.default_max_steps', 10));

            $limits[$id] = $maxSteps + 1;
        }

        return $limits;
    }

    public function assertValid(array $graph): void
    {
        $result = $this->validate($graph);

        if (! $result['valid']) {
            throw new InvalidArgumentException(implode(' ', $result['errors']));
        }
    }
}
