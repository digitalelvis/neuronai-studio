<?php

namespace ElvisLopesDigital\NeuronAIStudio\Codegen;

use ElvisLopesDigital\NeuronAIStudio\Runtime\GraphContext;
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

        $events = [];
        $executableNodes = [];
        $executionOrder = [];

        foreach ($nodes as $node) {
            $id = (string) ($node['id'] ?? '');
            $type = (string) ($node['type'] ?? '');

            if ($id === '' || in_array($type, ['start'], true)) {
                continue;
            }

            $executionOrder[] = $id;

            $inputEvent = $id === $startTargetId
                ? 'StartEvent'
                : $this->eventClassName($id);

            $outgoing = $context->outgoingEdges($id);
            $branchReturns = [];
            $returnType = 'StopEvent';

            if ($type === 'condition') {
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
            } elseif ($type === 'stop') {
                $returnType = 'StopEvent';
            } else {
                $targetId = $context->targetForHandle($id);
                if ($targetId !== null) {
                    $targetType = (string) ($nodeById[$targetId]['type'] ?? '');
                    $returnType = $this->eventClassName($targetId);

                    $events[$targetId] = ['id' => $targetId, 'className' => $returnType];
                }
            }

            if ($id === $startTargetId) {
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
            ];
        }

        return [
            'startTargetId' => $startTargetId,
            'nodes' => $executableNodes,
            'events' => array_values($events),
            'executionOrder' => $executionOrder,
        ];
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
