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

            if ($id === '' || in_array($type, ['start'], true)) {
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
                'data' => is_array($node['data'] ?? null) ? $node['data'] : [],
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
