<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Workflows;

use DigitalElvis\NeuronAIStudio\Codegen\WorkflowClassImporter;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Registry\WorkflowRegistry;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use Illuminate\Support\Str;
use Livewire\Component;

class Index extends Component
{
    public function delete(int $workflowId): void
    {
        $workflow = WorkflowDefinition::findOrFail($workflowId);

        if ($workflow->source === 'code') {
            return;
        }

        $workflow->delete();
    }

    public function importToStudio(string $ref): void
    {
        $importer = app(WorkflowClassImporter::class);

        $imported = match (true) {
            str_starts_with($ref, 'class:') => $importer->fromClass(Str::after($ref, 'class:')),
            str_starts_with($ref, 'json:') => $importer->fromJsonFile(Str::after($ref, 'json:')),
            default => null,
        };

        if ($imported === null || $importer->hasError($imported)) {
            session()->flash('error', $imported['error'] ?? 'Could not import workflow.');

            return;
        }

        $name = (string) $imported['name'];
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (WorkflowDefinition::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $workflow = WorkflowDefinition::create([
            'name' => $name,
            'slug' => $slug,
            'description' => (string) ($imported['description'] ?? ''),
            'graph' => $imported['graph'],
            'status' => (string) ($imported['status'] ?? 'draft'),
            'source' => 'studio',
            'locked' => false,
            'class_path' => null,
        ]);

        session()->flash('success', 'Workflow imported to studio.');
        $this->redirect(route('neuronai-studio.workflows.edit', $workflow));
    }

    public function render()
    {
        return view('neuronai-studio::livewire.workflows.index', [
            'workflows' => WorkflowDefinition::studio()->latest()->get(),
            'codeWorkflows' => app(WorkflowRegistry::class)->codeEntries(),
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [['label' => 'Workflows']],
            title: 'Workflows',
            headerActions: view('neuronai-studio::partials.header-actions.new-workflow')->render(),
        ));
    }
}
