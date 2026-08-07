<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Http\Livewire\Workflows\Editor;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use Livewire\Livewire;

class WorkflowEditorSaveTest extends TestCase
{
    public function test_save_graph_persists_metadata_from_payload(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Original Name',
            'slug' => 'original-name',
            'description' => 'Old description',
            'status' => 'draft',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        Livewire::test(Editor::class, ['workflow' => $workflow])
            ->call('saveGraph', $workflow->graph, [
                'name' => 'Renamed Workflow',
                'description' => 'Updated description',
                'status' => 'published',
            ])
            ->assertHasNoErrors();

        $workflow->refresh();

        $this->assertSame('Renamed Workflow', $workflow->name);
        $this->assertSame('Updated description', $workflow->description);
        $this->assertSame('published', $workflow->status);
        $this->assertSame('renamed-workflow', $workflow->slug);
    }

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

    public function test_render_excludes_current_workflow_from_workflows_for_canvas(): void
    {
        $current = WorkflowDefinition::create([
            'name' => 'Current Parent',
            'slug' => 'current-parent-'.uniqid(),
            'status' => 'draft',
            'source' => 'studio',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $other = WorkflowDefinition::create([
            'name' => 'Other Child',
            'slug' => 'other-child-'.uniqid(),
            'status' => 'draft',
            'source' => 'studio',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $html = Livewire::test(Editor::class, ['workflow' => $current])
            ->assertOk()
            ->html();

        $this->assertStringContainsString('workflows:', $html);
        $this->assertStringContainsString('"id":'.$other->id, $html);
        $this->assertStringContainsString('"name":"Other Child"', $html);
        $this->assertStringNotContainsString('"name":"Current Parent"', preg_replace('/workflowName:.*?,/s', '', $html) ?? $html);

        // Current workflow must not appear in the workflows picker payload.
        $this->assertDoesNotMatchRegularExpression(
            '/workflows:\s*\[[^\]]*"id":'.$current->id.'/',
            $html,
        );
    }
}
