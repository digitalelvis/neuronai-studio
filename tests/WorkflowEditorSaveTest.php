<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Http\Livewire\Workflows\Editor;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use Livewire\Livewire;

class WorkflowEditorSaveTest extends TestCase
{
    public function test_save_graph_keeps_deduplicated_slug_when_name_is_unchanged(): void
    {
        WorkflowDefinition::create([
            'name' => 'Parallel Support Triage with Human Review',
            'slug' => 'parallel-support-triage-with-human-review',
            'status' => 'draft',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $duplicate = WorkflowDefinition::create([
            'name' => 'Parallel Support Triage with Human Review',
            'slug' => 'parallel-support-triage-with-human-review-1',
            'status' => 'draft',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        Livewire::test(Editor::class, ['workflow' => $duplicate])
            ->call('saveGraph', $duplicate->graph)
            ->assertHasNoErrors();

        $duplicate->refresh();

        $this->assertSame('parallel-support-triage-with-human-review-1', $duplicate->slug);
    }

    public function test_save_assigns_unique_slug_when_name_changes_to_existing_slug(): void
    {
        WorkflowDefinition::create([
            'name' => 'Parallel Support Triage with Human Review',
            'slug' => 'parallel-support-triage-with-human-review',
            'status' => 'draft',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $workflow = WorkflowDefinition::create([
            'name' => 'Draft Copy',
            'slug' => 'draft-copy',
            'status' => 'draft',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        Livewire::test(Editor::class, ['workflow' => $workflow])
            ->set('name', 'Parallel Support Triage with Human Review')
            ->call('save')
            ->assertHasNoErrors();

        $workflow->refresh();

        $this->assertSame('parallel-support-triage-with-human-review-1', $workflow->slug);
    }
}
