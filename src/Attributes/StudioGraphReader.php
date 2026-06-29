<?php

namespace DigitalElvis\NeuronAIStudio\Attributes;

use ReflectionClass;

class StudioGraphReader
{
    /** @return array{name: string, description: string, status: string, graph: array<string, mixed>}|null */
    public function fromClass(string $class): ?array
    {
        if (! class_exists($class)) {
            return null;
        }

        $reflection = new ReflectionClass($class);
        $attributes = $reflection->getAttributes(StudioGraph::class);

        if ($attributes === []) {
            return null;
        }

        /** @var StudioGraph $instance */
        $instance = $attributes[0]->newInstance();

        return [
            'name' => $instance->name,
            'description' => $instance->description,
            'status' => $instance->status,
            'graph' => $instance->graph,
        ];
    }
}
