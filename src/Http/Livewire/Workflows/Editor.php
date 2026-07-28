<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Workflows;

use DigitalElvis\NeuronAIStudio\Codegen\CodegenDisabledException;
use DigitalElvis\NeuronAIStudio\Codegen\CodegenGuard;
use DigitalElvis\NeuronAIStudio\Codegen\WorkflowClassImporter;
use DigitalElvis\NeuronAIStudio\Codegen\WorkflowExporter;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Registry\McpRegistry;
use DigitalElvis\NeuronAIStudio\Registry\NodeTypeRegistry;
use DigitalElvis\NeuronAIStudio\Registry\OutputClassRegistry;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Models\ToolDefinition;
use DigitalElvis\NeuronAIStudio\Registry\ToolRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\GraphValidator;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use DigitalElvis\NeuronAIStudio\Support\ResolvesOptionalRouteModel;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use DigitalElvis\NeuronAIStudio\Support\ToolSchemaInspector;
use Illuminate\Support\Str;
use Livewire\Component;

class Editor extends Component
{
    use ResolvesOptionalRouteModel;

    public ?WorkflowDefinition $workflow = null;

    public string $name = '';

    public string $description = '';

    public string $status = 'draft';

    public array $graph = [];

    public string $validationMessage = '';

    public bool $readOnly = false;

    public ?string $linkedClassPath = null;

    public function mount(mixed $workflow = null): void
    {
        $class = request('class');
        $jsonRef = request('json');

        if (is_string($class) && $class !== '') {
            $this->mountFromCodeClass($class);

            return;
        }

        if (is_string($jsonRef) && $jsonRef !== '') {
            $this->mountFromJsonRef($jsonRef);

            return;
        }

        $this->workflow = $this->resolveOptionalRouteModel($workflow, WorkflowDefinition::class);
        $workflow = $this->workflow;

        if ($workflow?->exists) {
            $this->name = $workflow->name;
            $this->description = (string) $workflow->description;
            $this->status = $workflow->status;
            $this->graph = $workflow->graph ?? WorkflowDefinition::defaultGraph();
            $this->readOnly = (bool) $workflow->locked;
            $this->linkedClassPath = $workflow->class_path;
        } else {
            $this->graph = WorkflowDefinition::defaultGraph();
        }
    }

    public function saveGraph(array $graph): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->graph = $graph;
        $this->save();
    }

    /**
     * Persist ToolDefinition schema edits from the canvas Actions modal.
     *
     * @param  array{name?: string, description?: string|null, properties?: array<int, array{name: string, type?: string, description?: string|null, required?: bool}>}  $action
     * @return array{ok: bool, error?: string, tool?: array<string, mixed>}
     */
    public function updateToolDefinition(int $toolId, array $action): array
    {
        if ($this->readOnly) {
            return ['ok' => false, 'error' => 'Workflow is read-only.'];
        }

        $definition = ToolDefinition::find($toolId);

        if (! $definition) {
            return ['ok' => false, 'error' => 'Tool not found.'];
        }

        $name = trim((string) ($action['name'] ?? ''));
        $description = trim((string) ($action['description'] ?? ''));
        $properties = is_array($action['properties'] ?? null) ? $action['properties'] : [];

        if ($name === '' || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            return ['ok' => false, 'error' => 'Name must be a valid function name.'];
        }

        if ($description === '') {
            return ['ok' => false, 'error' => 'Description is required.'];
        }

        $inputSchema = [];

        foreach ($properties as $property) {
            if (! is_array($property) || empty($property['name'])) {
                continue;
            }

            $propName = trim((string) $property['name']);

            if ($propName === '' || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $propName)) {
                return ['ok' => false, 'error' => "Invalid property name: {$propName}"];
            }

            $type = (string) ($property['type'] ?? 'string');

            if (! in_array($type, ['string', 'integer', 'number', 'boolean'], true)) {
                return ['ok' => false, 'error' => "Invalid property type: {$type}"];
            }

            $inputSchema[] = [
                'name' => $propName,
                'type' => $type,
                'description' => isset($property['description']) ? (string) $property['description'] : '',
                'required' => (bool) ($property['required'] ?? false),
            ];
        }

        $config = is_array($definition->config) ? $definition->config : [];
        $config['tool_name'] = $name;

        $definition->update([
            'description' => $description,
            'input_schema' => $inputSchema,
            'config' => $config,
        ]);

        $enriched = app(ToolSchemaInspector::class)->enrich([
            'ref' => $definition->bindingRef(),
            'label' => $definition->name,
            'type' => $definition->type,
            'category' => 'studio',
            'description' => $definition->description,
        ]);

        return ['ok' => true, 'tool' => $enriched];
    }

    /** @return array{valid: bool, errors: array<int, string>} */
    public function validateGraphPayload(array $graph): array
    {
        return app(GraphValidator::class)->validate($graph);
    }

    public function applyImportedGraph(array $graph): void
    {
        if ($this->readOnly) {
            return;
        }

        $result = app(GraphValidator::class)->validate($graph);

        if (! $result['valid']) {
            return;
        }

        $this->graph = $graph;
    }

    public function save(): void
    {
        if ($this->readOnly) {
            return;
        }

        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:draft,published',
        ]);

        $payload = array_merge($validated, [
            'slug' => $this->resolveSlug($this->workflow),
            'graph' => $this->graph,
            'source' => 'studio',
            'locked' => false,
        ]);

        if ($this->workflow?->exists) {
            $this->workflow->update($payload);
        } else {
            $this->workflow = WorkflowDefinition::create($payload);
            $this->redirect(route('neuronai-studio.workflows.edit', $this->workflow));
        }

        session()->flash('success', 'Workflow saved.');
    }

    protected function resolveSlug(?WorkflowDefinition $existing = null): string
    {
        if ($existing?->exists && $existing->name === $this->name) {
            return $existing->slug;
        }

        $baseSlug = Str::slug($this->name);
        $slug = $baseSlug;
        $counter = 1;

        while (
            WorkflowDefinition::where('slug', $slug)
                ->when($existing?->exists, fn ($query) => $query->where('id', '!=', $existing->id))
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
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

        $trace = $runner->run($this->workflow, ['input' => 'Hello from workflow test']);

        $this->redirect(route('neuronai-studio.workflows.traces.show', $trace));
    }

    public function exportWorkflow(WorkflowExporter $exporter): void
    {
        try {
            CodegenGuard::ensureExport();
        } catch (CodegenDisabledException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        if (! $this->workflow?->exists) {
            $this->save();
        }

        $result = $exporter->exportWithMeta($this->workflow);
        $this->workflow->update(['class_path' => $result['fqcn']]);
        $this->linkedClassPath = $result['fqcn'];
        session()->flash('success', 'Exported '.count($result['files']).' native PHP file(s).');
    }

    /** @return array{code: string, className: string, namespace: string, fqcn: string, fileCount: int} */
    public function previewWorkflowCode(
        array $graph,
        string $name,
        string $description,
        string $status,
        WorkflowExporter $exporter,
    ): array {
        CodegenGuard::ensurePreview();

        $workflow = WorkflowDefinition::make([
            'name' => $name !== '' ? $name : 'Workflow',
            'slug' => Str::slug($name !== '' ? $name : 'workflow'),
            'description' => $description,
            'status' => $status !== '' ? $status : 'draft',
            'graph' => $graph,
        ]);

        return $exporter->previewMeta($workflow);
    }

    protected function mountFromCodeClass(string $class): void
    {
        $imported = app(WorkflowClassImporter::class)->fromClass($class);

        if ($imported === null || app(WorkflowClassImporter::class)->hasError($imported)) {
            session()->flash('error', $imported['error'] ?? 'Could not import workflow class.');
            $this->redirect(route('neuronai-studio.workflows.index'));

            return;
        }

        $this->hydrateFromImportedSource($imported, readOnly: true);
    }

    protected function mountFromJsonRef(string $jsonPath): void
    {
        $imported = app(WorkflowClassImporter::class)->fromJsonFile($jsonPath);

        if ($imported === null || app(WorkflowClassImporter::class)->hasError($imported)) {
            session()->flash('error', $imported['error'] ?? 'Could not import workflow JSON file.');
            $this->redirect(route('neuronai-studio.workflows.index'));

            return;
        }

        $this->hydrateFromImportedSource($imported, readOnly: true);
    }

    /** @param  array<string, mixed>  $imported */
    protected function hydrateFromImportedSource(array $imported, bool $readOnly): void
    {
        $classPath = (string) ($imported['class_path'] ?? '');

        $this->workflow = $this->upsertShadowRecord($classPath, $imported);
        $this->name = (string) $imported['name'];
        $this->description = (string) ($imported['description'] ?? '');
        $this->status = (string) ($imported['status'] ?? 'draft');
        $this->graph = $imported['graph'];
        $this->readOnly = $readOnly;
        $this->linkedClassPath = $classPath;
    }

    /** @param  array<string, mixed>  $imported */
    protected function upsertShadowRecord(string $classPath, array $imported): WorkflowDefinition
    {
        $slugBase = Str::slug((string) $imported['name']);
        $slugSuffix = Str::lower(Str::substr(md5($classPath), 0, 8));

        return WorkflowDefinition::updateOrCreate(
            ['class_path' => $classPath],
            [
                'name' => (string) $imported['name'],
                'slug' => "{$slugBase}-code-{$slugSuffix}",
                'description' => (string) ($imported['description'] ?? ''),
                'graph' => $imported['graph'],
                'status' => (string) ($imported['status'] ?? 'draft'),
                'source' => 'code',
                'locked' => true,
            ]
        );
    }

    public function render()
    {
        $title = $this->readOnly
            ? 'Preview Workflow'
            : ($this->workflow?->exists ? 'Edit Workflow' : 'Create Workflow');

        return view('neuronai-studio::livewire.workflows.editor', [
            'nodeTypes' => array_merge(
                app(NodeTypeRegistry::class)->forCanvas(),
                [
                    'note' => array_merge(
                        ['type' => 'note'],
                        config('neuronai-studio.node_types.note', [
                            'label' => 'Sticky Note',
                            'icon' => 'sticky',
                            'category' => 'utilities',
                        ]),
                    ),
                ],
            ),
            'providers' => app(ProviderRegistry::class)->labels(),
            'agents' => AgentDefinition::orderBy('name')->get(),
            'agentsForCanvas' => AgentDefinition::orderBy('name')->get(['id', 'name'])->values()->all(),
            'knowledgeBasesForCanvas' => KnowledgeBase::orderBy('name')->get(['id', 'name'])->values()->all(),
            'toolsForCanvas' => collect(app(ToolRegistry::class)->all())
                ->map(fn (array $tool) => app(ToolSchemaInspector::class)->enrich($tool))
                ->values()
                ->all(),
            'mcpServersForCanvas' => collect(app(McpRegistry::class)->all(includeDisabled: false))
                ->filter(fn (array $entry) => $entry['enabled'] ?? true)
                ->map(fn (array $entry, string $slug) => [
                    'slug' => $slug,
                    'label' => $entry['label'] ?? Str::headline($slug),
                    'description' => $entry['description'] ?? null,
                ])
                ->values()
                ->all(),
            'outputClassesForCanvas' => collect(app(OutputClassRegistry::class)->all())
                ->map(fn (array $outputClass) => [
                    'class' => $outputClass['class'],
                    'label' => $outputClass['label'],
                    'properties' => $outputClass['properties'] ?? [],
                ])
                ->values()
                ->all(),
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [
                ['label' => 'Workflows', 'url' => route('neuronai-studio.workflows.index')],
                ['label' => $this->name ?: ($this->workflow?->exists ? 'Edit' : 'New Workflow')],
            ],
            title: $title,
            contentFlush: true,
        ));
    }
}
