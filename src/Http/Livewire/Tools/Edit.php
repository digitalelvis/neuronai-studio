<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Tools;

use DigitalElvis\NeuronAIStudio\Codegen\CodegenDisabledException;
use DigitalElvis\NeuronAIStudio\Codegen\CodegenGuard;
use DigitalElvis\NeuronAIStudio\Codegen\ToolClassGenerator;
use DigitalElvis\NeuronAIStudio\Codegen\ToolClassImporter;
use DigitalElvis\NeuronAIStudio\Codegen\ToolExporter;
use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Models\ToolDefinition;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use Illuminate\Support\Str;
use Livewire\Component;

class Edit extends Component
{
    public ?ToolDefinition $tool = null;

    public string $toolKind = 'builder';

    public string $name = '';

    public string $toolName = '';

    public string $description = '';

    public string $method = 'GET';

    public string $url = '';

    public string $headersJson = '{}';

    public string $invokeBody = '';

    /** @var array<int, array{name: string, type: string, description: string, required: bool}> */
    public array $inputSchema = [];

    public ?int $knowledgeBaseId = null;

    public ?int $topK = null;

    public ?float $threshold = null;

    public function mount(?ToolDefinition $tool = null): void
    {
        $this->tool = $tool;

        if ($tool?->exists) {
            $this->loadFromDefinition($tool);

            return;
        }

        $importClass = request('import');

        if (is_string($importClass) && $importClass !== '') {
            $this->loadFromClass($importClass);

            return;
        }

        $kind = request('kind', 'builder');

        if ($kind === 'webhook') {
            $this->toolKind = 'webhook';
        } elseif ($kind === 'rag') {
            $this->toolKind = 'rag';
            $this->toolName = 'search_knowledge_base';
            $this->description = 'Search the linked knowledge base for relevant documents.';
        } else {
            $this->toolKind = 'builder';
        }

        if ($this->toolKind === 'builder') {
            $this->inputSchema = [
                ['name' => 'example', 'type' => 'string', 'description' => 'Example argument', 'required' => true],
            ];
            $this->invokeBody = "return 'Result for: '.\$example;";
        }
    }

    protected function loadFromDefinition(ToolDefinition $tool): void
    {
        $this->name = $tool->name;
        $this->description = $tool->description;
        $this->toolName = $tool->config['tool_name'] ?? Str::slug($tool->slug, '_');
        $this->inputSchema = $tool->input_schema ?? [];
        $this->invokeBody = trim((string) ($tool->config['invoke_body'] ?? ''));

        if ($tool->type === 'webhook') {
            $this->toolKind = 'webhook';
            $this->method = $tool->config['method'] ?? 'GET';
            $this->url = $tool->config['url'] ?? '';
            $this->headersJson = json_encode($tool->config['headers'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } elseif ($tool->type === 'rag') {
            $this->toolKind = 'rag';
            $this->knowledgeBaseId = isset($tool->config['knowledge_base_id'])
                ? (int) $tool->config['knowledge_base_id']
                : null;
            $this->topK = isset($tool->config['top_k']) ? (int) $tool->config['top_k'] : null;
            $this->threshold = isset($tool->config['threshold']) ? (float) $tool->config['threshold'] : null;
        } else {
            $this->toolKind = 'builder';
        }
    }

    protected function loadFromClass(string $class): void
    {
        $imported = app(ToolClassImporter::class)->fromClass($class);

        if ($imported === null) {
            session()->flash('success', 'Could not import class. Starting with empty builder.');
            $this->invokeBody = "return 'Executed';";

            return;
        }

        $this->toolKind = 'builder';
        $this->name = (string) $imported['name'];
        $this->toolName = (string) $imported['tool_name'];
        $this->description = (string) $imported['description'];
        $this->inputSchema = $imported['input_schema'] ?: $this->inputSchema;
        $this->invokeBody = (string) $imported['invoke_body'];
    }

    public function updatedName(string $value): void
    {
        if ($this->toolName === '' || $this->toolName === Str::slug($this->toolName, '_')) {
            $this->toolName = Str::slug($value, '_');
        }
    }

    public function addProperty(): void
    {
        $this->inputSchema[] = [
            'name' => '',
            'type' => 'string',
            'description' => '',
            'required' => false,
        ];
    }

    public function removeProperty(int $index): void
    {
        unset($this->inputSchema[$index]);
        $this->inputSchema = array_values($this->inputSchema);
    }

    public function save(ToolExporter $exporter): void
    {
        if ($this->toolKind === 'webhook') {
            $this->saveWebhook();

            return;
        }

        if ($this->toolKind === 'rag') {
            $this->saveRag();

            return;
        }

        $this->saveBuilder($exporter);
    }

    /** @param  array<string, mixed>  $payload */
    public function saveFromReact(array $payload, ToolExporter $exporter): void
    {
        $this->toolKind = (string) ($payload['toolKind'] ?? 'builder');
        $this->name = (string) ($payload['name'] ?? '');
        $this->toolName = (string) ($payload['toolName'] ?? '');
        $this->description = (string) ($payload['description'] ?? '');
        $this->method = (string) ($payload['method'] ?? 'GET');
        $this->url = (string) ($payload['url'] ?? '');
        $this->headersJson = (string) ($payload['headersJson'] ?? '{}');
        $this->invokeBody = (string) ($payload['invokeBody'] ?? '');
        $this->inputSchema = $payload['inputSchema'] ?? [];
        $this->knowledgeBaseId = isset($payload['knowledgeBaseId']) && $payload['knowledgeBaseId'] !== ''
            ? (int) $payload['knowledgeBaseId']
            : null;
        $this->topK = isset($payload['topK']) && $payload['topK'] !== '' && $payload['topK'] !== null
            ? (int) $payload['topK']
            : null;
        $this->threshold = isset($payload['threshold']) && $payload['threshold'] !== '' && $payload['threshold'] !== null
            ? (float) $payload['threshold']
            : null;

        $this->save($exporter);
    }

    /** @param  array<string, mixed>  $payload */
    public function previewFromReact(array $payload): string
    {
        CodegenGuard::ensurePreview();

        $toolKind = (string) ($payload['toolKind'] ?? 'builder');

        if ($toolKind !== 'builder') {
            return '';
        }

        return app(ToolClassGenerator::class)->generate([
            'class_name' => Str::studly((string) ($payload['toolName'] ?? 'example')).'Tool',
            'tool_name' => (string) ($payload['toolName'] ?? 'example_tool'),
            'description' => (string) ($payload['description'] ?? 'Tool description'),
            'input_schema' => $payload['inputSchema'] ?? [],
            'invoke_body' => (string) ($payload['invokeBody'] ?? "        return 'Executed';"),
        ]);
    }

    protected function saveBuilder(ToolExporter $exporter): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'toolName' => 'required|string|max:255|regex:/^[a-z0-9_]+$/',
            'description' => 'required|string',
            'invokeBody' => 'required|string',
            'inputSchema' => 'array',
            'inputSchema.*.name' => 'required|string|max:255|regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/',
            'inputSchema.*.type' => 'required|in:string,integer,number,boolean',
            'inputSchema.*.description' => 'nullable|string',
        ]);

        $className = Str::studly($validated['toolName']).'Tool';

        $config = [
            'tool_name' => $validated['toolName'],
            'class_name' => $className,
            'invoke_body' => $this->invokeBody,
        ];

        if ($this->tool?->exists && isset($this->tool->config['class_path'])) {
            $config['class_path'] = $this->tool->config['class_path'];
        }

        $payload = [
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'type' => 'builder',
            'description' => $validated['description'],
            'input_schema' => $this->inputSchema,
            'config' => $config,
        ];

        if ($this->tool?->exists) {
            $this->tool->update($payload);
        } else {
            $this->tool = ToolDefinition::create($payload);
        }

        if (CodegenGuard::canExport()) {
            $files = $exporter->export($this->tool->fresh());
            session()->flash('success', 'Tool saved and exported to '.implode(', ', $files));
        } else {
            session()->flash('success', 'Tool saved. CodeGen export is disabled — PHP class was not written to disk.');
        }

        $this->redirect(route('neuronai-studio.tools.show', $this->tool));
    }

    protected function saveWebhook(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'method' => 'required|in:GET,POST,PUT,PATCH,DELETE',
            'url' => 'required|url',
            'headersJson' => 'nullable|string',
            'inputSchema' => 'array',
            'inputSchema.*.name' => 'required|string|max:255',
            'inputSchema.*.type' => 'required|in:string,integer,number,boolean',
            'inputSchema.*.description' => 'nullable|string',
        ]);

        $headers = json_decode($validated['headersJson'] ?: '{}', true);

        if (! is_array($headers)) {
            $this->addError('headersJson', 'Headers must be valid JSON object.');

            return;
        }

        $payload = [
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'type' => 'webhook',
            'description' => $validated['description'],
            'input_schema' => $this->inputSchema,
            'config' => [
                'method' => $validated['method'],
                'url' => $validated['url'],
                'headers' => $headers,
            ],
        ];

        if ($this->tool?->exists) {
            $this->tool->update($payload);
        } else {
            $this->tool = ToolDefinition::create($payload);
        }

        session()->flash('success', 'Webhook tool saved successfully.');

        $this->redirect(route('neuronai-studio.tools.show', $this->tool));
    }

    protected function saveRag(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'toolName' => 'required|string|max:255|regex:/^[a-z0-9_]+$/',
            'description' => 'required|string',
            'knowledgeBaseId' => 'required|integer|exists:knowledge_bases,id',
            'topK' => 'nullable|integer|min:1|max:100',
            'threshold' => 'nullable|numeric|min:0|max:1',
        ]);

        $config = [
            'tool_name' => $validated['toolName'],
            'knowledge_base_id' => $validated['knowledgeBaseId'],
        ];

        if ($validated['topK'] !== null) {
            $config['top_k'] = $validated['topK'];
        }

        if ($validated['threshold'] !== null) {
            $config['threshold'] = $validated['threshold'];
        }

        $payload = [
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'type' => 'rag',
            'description' => $validated['description'],
            'input_schema' => [
                [
                    'name' => 'query',
                    'type' => 'string',
                    'description' => 'Natural language search query',
                    'required' => true,
                ],
            ],
            'config' => $config,
        ];

        if ($this->tool?->exists) {
            $this->tool->update($payload);
        } else {
            $this->tool = ToolDefinition::create($payload);
        }

        session()->flash('success', 'RAG knowledge base tool saved successfully.');

        $this->redirect(route('neuronai-studio.tools.show', $this->tool));
    }

    public function exportPhp(ToolExporter $exporter): void
    {
        try {
            CodegenGuard::ensureExport();
        } catch (CodegenDisabledException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        if (! $this->tool?->exists) {
            $this->addError('name', 'Save the tool before exporting.');

            return;
        }

        $files = $exporter->export($this->tool->fresh());
        session()->flash('success', 'Exported: '.implode(', ', $files));
    }

    public function getGeneratedPreviewProperty(): string
    {
        CodegenGuard::ensurePreview();

        return app(ToolClassGenerator::class)->generate([
            'class_name' => Str::studly($this->toolName ?: 'example').'Tool',
            'tool_name' => $this->toolName ?: 'example_tool',
            'description' => $this->description ?: 'Tool description',
            'input_schema' => $this->inputSchema,
            'invoke_body' => $this->invokeBody ?: "        return 'Executed';",
        ]);
    }

    public function getInvokeSignatureProperty(): string
    {
        $params = app(ToolClassGenerator::class)->buildInvokeParams($this->inputSchema);

        return '__invoke('.($params ?: '').'): string';
    }

    public function render()
    {
        $knowledgeBases = KnowledgeBase::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('neuronai-studio::livewire.tools.edit', [
            'knowledgeBases' => $knowledgeBases,
        ])
            ->layout('neuronai-studio::layouts.app', StudioLayout::params(
                breadcrumbs: [
                    ['label' => 'Tools', 'url' => route('neuronai-studio.tools.index')],
                    ['label' => $this->tool?->exists ? $this->name : 'New Tool'],
                ],
                title: $this->tool?->exists ? 'Edit Tool' : 'Create Tool',
                contentFlush: true,
            ));
    }
}
