<?php

namespace DigitalElvis\NeuronAIStudio\Codegen;

use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use Illuminate\Support\Str;
use InvalidArgumentException;

class GraphTranspiler
{
    /**
     * @param  array{version?: int, nodes?: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>, viewport?: array<string, float|int>}  $graph
     * @return array{
     *     startTargetId: string|null,
     *     nodes: array<string, array{id: string, type: string, data: array<string, mixed>, className: string, inputEvent: string, returnType: string, branchReturns: array<string, string>}>,
     *     events: array<string, array{id: string, className: string}>,
     *     executionOrder: array<int, string>
     * }
     */
    public function transpile(array $graph): array
    {
        $nodes = $graph['nodes'] ?? [];
        $edges = $graph['edges'] ?? [];
        $context = new GraphContext($nodes, $edges);

        $nodeById = [];
        foreach ($nodes as $node) {
            $id = (string) ($node['id'] ?? '');
            if ($id !== '') {
                $nodeById[$id] = $node;
            }
        }

        $startTargetId = null;
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === 'start') {
                $startTargetId = $context->targetForHandle((string) $node['id']);
                break;
            }
        }

        $parallel = $this->parallelMeta($nodes, $context);

        $events = [];
        $executableNodes = [];
        $executionOrder = [];

        foreach ($parallel['events'] as $eventClass => $eventDef) {
            $events[$eventClass] = $eventDef;
        }

        foreach ($nodes as $node) {
            $id = (string) ($node['id'] ?? '');
            $type = (string) ($node['type'] ?? '');
            $data = is_array($node['data'] ?? null) ? $node['data'] : [];

            if ($id === '' || in_array($type, ['start'], true)) {
                continue;
            }

            // Tool Mode agents / run_workflow nodes are binding-only — not linear workflow Nodes.
            if (in_array($type, ['agent', 'run_workflow'], true) && $this->isToolModeEnabled($data)) {
                continue;
            }

            $executionOrder[] = $id;

            $inputEvent = $id === $startTargetId
                ? 'StartEvent'
                : ($parallel['joinInput'][$id] ?? $this->eventClassName($id));

            $outgoing = $context->outgoingEdges($id);
            $branchReturns = [];
            $returnType = 'StopEvent';
            $forkMeta = null;
            $stopResultKey = null;

            if ($type === 'fork') {
                $fork = $parallel['forks'][$id];
                $returnType = $fork['eventClass'];
                $forkMeta = $fork;
                $events[$fork['eventClass']] = [
                    'id' => $id,
                    'className' => $fork['eventClass'],
                    'kind' => 'parallel',
                ];
            } elseif ($type === 'condition') {
                foreach (['true', 'false'] as $handle) {
                    $targetId = $context->targetForHandle($id, $handle);
                    if ($targetId === null) {
                        continue;
                    }

                    $targetType = (string) ($nodeById[$targetId]['type'] ?? '');
                    $eventName = $this->eventClassName($targetId);

                    $branchReturns[$handle] = $eventName;
                    $events[$targetId] = ['id' => $targetId, 'className' => $eventName];
                }

                $returnTypes = array_values(array_unique($branchReturns));
                $returnType = count($returnTypes) === 1
                    ? $returnTypes[0]
                    : implode('|', $returnTypes);
            } elseif ($type === 'intent_classifier') {
                $intents = is_array($data['intents'] ?? null) ? $data['intents'] : [];
                foreach ($intents as $intent) {
                    $handle = is_array($intent) ? trim((string) ($intent['id'] ?? '')) : '';
                    if ($handle === '') {
                        continue;
                    }

                    $targetId = $context->targetForHandle($id, $handle);
                    if ($targetId === null) {
                        continue;
                    }

                    $eventName = $this->eventClassName($targetId);
                    $branchReturns[$handle] = $eventName;
                    $events[$targetId] = ['id' => $targetId, 'className' => $eventName];
                }

                $returnTypes = array_values(array_unique($branchReturns));
                $returnType = count($returnTypes) === 1
                    ? ($returnTypes[0] ?? 'StopEvent')
                    : (count($returnTypes) > 1 ? implode('|', $returnTypes) : 'StopEvent');
            } elseif ($type === 'loop') {
                foreach (['continue', 'exit'] as $handle) {
                    $targetId = $context->targetForHandle($id, $handle);
                    if ($targetId === null) {
                        continue;
                    }

                    $eventName = $this->eventClassName($targetId);

                    $branchReturns[$handle] = $eventName;
                    $events[$targetId] = ['id' => $targetId, 'className' => $eventName];
                }

                $returnTypes = array_values(array_unique($branchReturns));
                $returnType = count($returnTypes) === 1
                    ? $returnTypes[0]
                    : implode('|', $returnTypes);
            } elseif ($type === 'stop') {
                $returnType = 'StopEvent';
            } else {
                $targetId = $context->targetForHandle($id);
                if ($targetId !== null) {
                    $targetType = (string) ($nodeById[$targetId]['type'] ?? '');

                    if ($targetType === 'join') {
                        // Parallel branch terminal: return its result to the executor,
                        // which collects branch results into the ParallelEvent.
                        $returnType = 'StopEvent';
                        $stopResultKey = $this->resultKey($type, is_array($node['data'] ?? null) ? $node['data'] : []);
                    } else {
                        $returnType = $this->eventClassName($targetId);
                        $events[$targetId] = ['id' => $targetId, 'className' => $returnType];
                    }
                }
            }

            if ($id === $startTargetId && $type !== 'fork') {
                $firstTarget = $context->targetForHandle($id);
                if ($firstTarget !== null && ($nodeById[$firstTarget]['type'] ?? '') !== 'stop') {
                    $events[$firstTarget] = [
                        'id' => $firstTarget,
                        'className' => $this->eventClassName($firstTarget),
                    ];
                }
            }

            $executableNodes[$id] = [
                'id' => $id,
                'type' => $type,
                'data' => $this->nodeDataForPlan($id, $type, $data, $context),
                'className' => $this->nodeClassName($id),
                'inputEvent' => $inputEvent,
                'returnType' => $returnType,
                'branchReturns' => $branchReturns,
                'parallel' => $forkMeta,
                'stopResultKey' => $stopResultKey,
            ];
        }

        return [
            'startTargetId' => $startTargetId,
            'nodes' => $executableNodes,
            'events' => array_values($events),
            'executionOrder' => $executionOrder,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function nodeDataForPlan(string $id, string $type, array $data, GraphContext $context): array
    {
        if ($type !== 'agent') {
            return $data;
        }

        $bindings = $this->snapshotToolBindings($context->toolBindingsFor($id), $context);
        if ($bindings === []) {
            return $data;
        }

        $mode = (string) ($data['config_mode'] ?? '');
        $isInline = $mode === 'inline'
            || ($mode !== 'existing' && (! isset($data['agent_id']) || $data['agent_id'] === '' || $data['agent_id'] === null));

        if ($isInline) {
            $data['tools'] = $bindings;
        } else {
            // Existing agents keep AgentDefinition tools at runtime; canvas bindings append.
            $data['canvas_tools'] = $bindings;
        }

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>  $bindings
     * @return array<int, array<string, mixed>>
     */
    protected function snapshotToolBindings(array $bindings, GraphContext $context): array
    {
        $snapshotted = [];

        foreach ($bindings as $binding) {
            if (! is_array($binding) || empty($binding['ref'])) {
                continue;
            }

            $ref = (string) $binding['ref'];
            if (! str_starts_with($ref, 'node:')) {
                $snapshotted[] = $binding;

                continue;
            }

            $nodeId = substr($ref, strlen('node:'));
            $node = is_array($binding['node'] ?? null) ? $binding['node'] : [];
            $data = is_array($node['data'] ?? null) ? $node['data'] : [];
            $nodeType = (string) ($node['type'] ?? 'agent');
            $exposure = is_array($binding['exposure'] ?? null) ? $binding['exposure'] : [];

            if ($nodeType === 'run_workflow') {
                $snapshotted[] = $this->snapshotWorkflowAsTool($exposure, $data);

                continue;
            }

            $agentConfig = $this->snapshotSpecialistConfig($data, $nodeId, $context);
            $slug = trim((string) ($exposure['slug'] ?? ''));
            if ($slug === '') {
                $slug = (string) (
                    config('neuronai-studio.node_types.agent.tool_exposure.slug_prefix')
                    ?: 'call_agent'
                );
            }

            $description = trim((string) ($exposure['description'] ?? ''));
            if ($description === '') {
                $description = (string) (
                    config('neuronai-studio.node_types.agent.tool_exposure.default_description')
                    ?: 'Delegate a task to this specialized agent.'
                );
            }

            $inputDescription = 'Task for the specialist';
            $parameters = is_array($exposure['parameters'] ?? null) ? $exposure['parameters'] : [];
            $input = is_array($parameters['input'] ?? null) ? $parameters['input'] : [];
            if (is_string($input['description'] ?? null) && trim((string) $input['description']) !== '') {
                $inputDescription = trim((string) $input['description']);
            }

            $snapshotted[] = [
                'ref' => 'node_as_tool',
                'slug' => $slug,
                'description' => $description,
                'input_description' => $inputDescription,
                'agent_config' => $agentConfig,
            ];
        }

        return $snapshotted;
    }

    /**
     * @param  array<string, mixed>  $exposure
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function snapshotWorkflowAsTool(array $exposure, array $data): array
    {
        $slug = trim((string) ($exposure['slug'] ?? ''));
        if ($slug === '') {
            $slug = (string) (
                config('neuronai-studio.node_types.run_workflow.tool_exposure.slug_prefix')
                ?: 'run_workflow'
            );
        }

        $description = trim((string) ($exposure['description'] ?? ''));
        if ($description === '') {
            $description = (string) (
                config('neuronai-studio.node_types.run_workflow.tool_exposure.default_description')
                ?: 'Execute another workflow in this project.'
            );
        }

        $inputDescription = 'Message / task for the child workflow';
        $parameters = is_array($exposure['parameters'] ?? null) ? $exposure['parameters'] : [];
        $input = is_array($parameters['input'] ?? null) ? $parameters['input'] : [];
        if (is_string($input['description'] ?? null) && trim((string) $input['description']) !== '') {
            $inputDescription = trim((string) $input['description']);
        }

        $stateMap = [];
        if (is_array($data['state_map'] ?? null)) {
            foreach ($data['state_map'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $key = isset($row['key']) && is_string($row['key']) ? trim($row['key']) : '';
                if ($key === '') {
                    continue;
                }
                $stateMap[] = [
                    'key' => $key,
                    'value' => $row['value'] ?? null,
                ];
            }
        }

        return [
            'ref' => 'workflow_as_tool',
            'slug' => $slug,
            'description' => $description,
            'input_description' => $inputDescription,
            'node_data' => [
                'workflow_id' => (string) ($data['workflow_id'] ?? ''),
                'message' => (string) ($data['message'] ?? ''),
                'state_map' => $stateMap,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function snapshotSpecialistConfig(array $data, string $nodeId, GraphContext $context): array
    {
        $mode = (string) ($data['config_mode'] ?? '');
        $agentId = $data['agent_id'] ?? null;
        $isExisting = $mode === 'existing'
            || ($mode !== 'inline' && $agentId !== null && $agentId !== '');

        $nestedTools = $context->toolBindingsFor($nodeId);

        if ($isExisting && $agentId !== null && $agentId !== '') {
            $definition = \DigitalElvis\NeuronAIStudio\Models\AgentDefinition::query()->find($agentId);
            if ($definition !== null) {
                return [
                    'provider' => $definition->provider,
                    'model' => $definition->model,
                    'instructions' => $definition->instructions,
                    'tools' => array_values(array_merge(
                        is_array($definition->tools) ? $definition->tools : [],
                        $nestedTools,
                    )),
                ];
            }
        }

        return [
            'provider' => $data['provider'] ?? config('neuronai-studio.default_provider'),
            'model' => $data['model'] ?? config('neuronai-studio.default_model'),
            'instructions' => $data['instructions'] ?? 'You are a helpful AI assistant.',
            'tools' => $nestedTools,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function isToolModeEnabled(array $data): bool
    {
        return filter_var($data['tool_mode'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Precomputes fork/join parallel metadata so branch input events, the
     * ParallelEvent subclass, and the join node input override are known before
     * per-node code generation.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array{
     *     forks: array<string, array{eventClass: string, joinId: string, branches: array<string, array{entryId: string, eventClass: string}>}>,
     *     joinInput: array<string, string>,
     *     events: array<string, array{id: string, className: string, kind?: string}>
     * }
     */
    protected function parallelMeta(array $nodes, GraphContext $context): array
    {
        $forks = [];
        $joinInput = [];
        $events = [];

        foreach ($nodes as $node) {
            if (($node['type'] ?? '') !== 'fork') {
                continue;
            }

            $forkId = (string) ($node['id'] ?? '');
            if ($forkId === '') {
                continue;
            }

            $eventClass = Str::studly($forkId).'ParallelEvent';

            $joinId = $context->targetForHandle($forkId, 'default');
            if (! is_string($joinId) || $joinId === '') {
                $joinId = (string) ($node['data']['join'] ?? '');
            }

            $branches = [];
            foreach ($context->outgoingEdges($forkId) as $edge) {
                $handle = (string) ($edge['sourceHandle'] ?? 'default');
                $target = (string) ($edge['target'] ?? '');

                if ($handle === 'default' || $target === '') {
                    continue;
                }

                $branchEventClass = $this->eventClassName($target);
                $branches[$handle] = ['entryId' => $target, 'eventClass' => $branchEventClass];
                $events[$branchEventClass] = ['id' => $target, 'className' => $branchEventClass];
            }

            $forks[$forkId] = [
                'eventClass' => $eventClass,
                'joinId' => (string) $joinId,
                'branches' => $branches,
            ];

            if (is_string($joinId) && $joinId !== '') {
                $joinInput[$joinId] = $eventClass;
            }
        }

        return [
            'forks' => $forks,
            'joinInput' => $joinInput,
            'events' => $events,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resultKey(string $type, array $data): string
    {
        return (string) ($data['output_key'] ?? $data['key'] ?? 'result');
    }

    public function nodeClassName(string $nodeId): string
    {
        return Str::studly($nodeId).'Node';
    }

    public function eventClassName(string $nodeId): string
    {
        return Str::studly($nodeId).'Event';
    }

    /**
     * @param  array<string, array{className: string}>  $events
     * @return array<int, string>
     */
    public function uniqueEventImports(array $events, string $eventsNamespace): array
    {
        $imports = [];

        foreach ($events as $event) {
            $imports[] = "{$eventsNamespace}\\{$event['className']}";
        }

        sort($imports);

        return array_values(array_unique($imports));
    }

    public function assertPlan(array $plan): void
    {
        if ($plan['startTargetId'] === null) {
            throw new InvalidArgumentException('Graph must have a start node connected to at least one target.');
        }
    }
}
