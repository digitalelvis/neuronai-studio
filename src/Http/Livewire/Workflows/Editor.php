<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Workflows;

use ElvisLopesDigital\NeuronAIStudio\Codegen\WorkflowExporter;
use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowDefinition;
use ElvisLopesDigital\NeuronAIStudio\Registry\NodeTypeRegistry;
use ElvisLopesDigital\NeuronAIStudio\Registry\ToolRegistry;
use ElvisLopesDigital\NeuronAIStudio\Runtime\GraphValidator;
use ElvisLopesDigital\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Support\Str;
use Livewire\Component;

class Editor extends Component
{
    public ?WorkflowDefinition $workflow = null;

    public string $name = '';

    public string $description = '';

    public string $status = 'draft';

    public array $graph = [];

    public string $validationMessage = '';

    public function mount(?WorkflowDefinition $workflow = null): void
    {
        $this->workflow = $workflow;

        if ($workflow?->exists) {
            $this->name = $workflow->name;
            $this->description = (string) $workflow->description;
            $this->status = $workflow->status;
            $this->graph = $workflow->graph ?? WorkflowDefinition::defaultGraph();
        } else {
            $this->graph = WorkflowDefinition::defaultGraph();
        }
    }

    public function saveGraph(array $graph): void
    {
        $this->graph = $graph;
        $this->save();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:draft,published',
        ]);

        $payload = array_merge($validated, [
            'slug' => Str::slug($this->name),
            'graph' => $this->graph,
        ]);

        if ($this->workflow?->exists) {
            $this->workflow->update($payload);
        } else {
            $this->workflow = WorkflowDefinition::create($payload);
            $this->redirect(route('neuronai-studio.workflows.edit', $this->workflow));
        }

        session()->flash('success', 'Workflow saved.');
    }

    public function validateGraph(GraphValidator $validator): void
    {
        $result = $validator->validate($this->graph);
        $this->validationMessage = $result['valid']
            ? 'Graph is valid.'
            : implode(' ', $result['errors']);
    }

    public function runWorkflow(WorkflowRunner $runner): void
    {
        if (! $this->workflow?->exists) {
            $this->save();
        }

        $run = $runner->run($this->workflow, ['input' => 'Hello from workflow test']);

        $this->redirect(route('neuronai-studio.workflows.runs.show', $run));
    }

    public function exportWorkflow(WorkflowExporter $exporter): void
    {
        if (! $this->workflow?->exists) {
            $this->save();
        }

        $files = $exporter->export($this->workflow);
        session()->flash('success', 'Exported '.count($files).' file(s).');
    }

    public function render()
    {
        return view('neuronai-studio::livewire.workflows.editor', [
            'nodeTypes' => app(NodeTypeRegistry::class)->forCanvas(),
            'agents' => AgentDefinition::orderBy('name')->get(),
            'agentsForCanvas' => AgentDefinition::orderBy('name')->get(['id', 'name'])->values()->all(),
            'toolsForCanvas' => collect(app(ToolRegistry::class)->all())
                ->map(fn (array $tool) => ['ref' => $tool['ref'], 'label' => $tool['label']])
                ->values()
                ->all(),
        ])->layout('neuronai-studio::layouts.app', [
            'title' => $this->workflow?->exists ? 'Edit Workflow' : 'Create Workflow',
        ]);
    }
}
