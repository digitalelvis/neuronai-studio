<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Http\Livewire\Workflows\Editor;
use DigitalElvis\NeuronAIStudio\Http\Livewire\Workflows\Index;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Tests\Fixtures\SampleStudioWorkflow;
use Livewire\Livewire;

class WorkflowCodeBridgeTest extends TestCase
{
    public function test_preview_mount_creates_shadow_record(): void
    {
        Livewire::withQueryParams(['class' => SampleStudioWorkflow::class])
            ->test(Editor::class)
            ->assertSet('readOnly', true)
            ->assertSet('linkedClassPath', SampleStudioWorkflow::class)
            ->assertSet('name', 'Sample Workflow');

        $this->assertDatabaseHas('workflow_definitions', [
            'class_path' => SampleStudioWorkflow::class,
            'source' => 'code',
            'locked' => true,
        ]);
    }

    public function test_import_to_studio_creates_editable_copy(): void
    {
        Livewire::test(Index::class)
            ->call('importToStudio', 'class:'.SampleStudioWorkflow::class)
            ->assertRedirect();

        $this->assertDatabaseHas('workflow_definitions', [
            'name' => 'Sample Workflow',
            'source' => 'studio',
            'locked' => false,
            'class_path' => null,
        ]);
    }

    public function test_studio_scope_excludes_code_shadow_records(): void
    {
        WorkflowDefinition::create([
            'name' => 'Studio One',
            'slug' => 'studio-one',
            'graph' => WorkflowDefinition::defaultGraph(),
            'status' => 'draft',
            'source' => 'studio',
        ]);

        WorkflowDefinition::create([
            'name' => 'Code Shadow',
            'slug' => 'code-shadow',
            'graph' => WorkflowDefinition::defaultGraph(),
            'status' => 'draft',
            'source' => 'code',
            'class_path' => SampleStudioWorkflow::class,
            'locked' => true,
        ]);

        $this->assertSame(1, WorkflowDefinition::studio()->count());
    }
}
