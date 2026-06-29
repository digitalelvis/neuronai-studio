<?php

namespace ElvisLopesDigital\NeuronAIStudio\Tests\Fixtures;

use ElvisLopesDigital\NeuronAIStudio\Contracts\StudioWorkflow;
use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowDefinition;

class SampleStudioWorkflow implements StudioWorkflow
{
    public static function studioMeta(): array
    {
        return [
            'name' => 'Sample Workflow',
            'description' => 'Fixture workflow for tests',
            'status' => 'draft',
        ];
    }

    public static function studioGraph(): array
    {
        $graph = WorkflowDefinition::defaultGraph();
        $graph['nodes'][] = [
            'id' => 'set_1',
            'type' => 'set_state',
            'position' => ['x' => 300, 'y' => 200],
            'data' => ['key' => 'greeting', 'value' => 'Hello'],
        ];

        return $graph;
    }
}
