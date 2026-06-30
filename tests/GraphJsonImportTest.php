<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Http\Livewire\Workflows\Editor;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use Livewire\Livewire;

class GraphJsonImportTest extends TestCase
{
    public function test_validate_graph_payload_accepts_valid_graph(): void
    {
        $graph = WorkflowDefinition::defaultGraph();

        Livewire::test(Editor::class)
            ->call('validateGraphPayload', $graph)
            ->assertReturned(['valid' => true, 'errors' => []]);
    }

    public function test_validate_graph_payload_rejects_invalid_graph(): void
    {
        Livewire::test(Editor::class)
            ->call('validateGraphPayload', ['nodes' => [], 'edges' => []])
            ->assertReturned(fn (array $result) => $result['valid'] === false && ! empty($result['errors']));
    }

    public function test_apply_imported_graph_updates_in_memory_graph(): void
    {
        $graph = WorkflowDefinition::defaultGraph();
        $graph['nodes'][] = [
            'id' => 'set_1',
            'type' => 'set_state',
            'position' => ['x' => 300, 'y' => 200],
            'data' => ['key' => 'foo', 'value' => 'bar'],
        ];

        Livewire::test(Editor::class)
            ->call('applyImportedGraph', $graph)
            ->assertSet('graph', $graph);
    }

    public function test_apply_imported_graph_is_ignored_when_read_only(): void
    {
        $original = WorkflowDefinition::defaultGraph();
        $modified = $original;
        $modified['nodes'][] = [
            'id' => 'set_1',
            'type' => 'set_state',
            'position' => ['x' => 300, 'y' => 200],
            'data' => ['key' => 'foo', 'value' => 'bar'],
        ];

        Livewire::test(Editor::class)
            ->set('readOnly', true)
            ->set('graph', $original)
            ->call('applyImportedGraph', $modified)
            ->assertSet('graph', $original);
    }
}
