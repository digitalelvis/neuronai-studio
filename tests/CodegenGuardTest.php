<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Codegen\AgentExporter;
use DigitalElvis\NeuronAIStudio\Codegen\CodegenDisabledException;
use DigitalElvis\NeuronAIStudio\Codegen\CodegenGuard;
use DigitalElvis\NeuronAIStudio\Codegen\ToolExporter;
use DigitalElvis\NeuronAIStudio\Codegen\WorkflowExporter;
use DigitalElvis\NeuronAIStudio\Http\Livewire\Tools\Edit;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\ToolDefinition;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

class CodegenGuardTest extends TestCase
{
    protected function setCodegen(bool $enabled, bool $export, bool $preview): void
    {
        config([
            'neuronai-studio.codegen.enabled' => $enabled,
            'neuronai-studio.codegen.export' => $export,
            'neuronai-studio.codegen.preview' => $preview,
        ]);
    }

    protected function exportPath(): string
    {
        $path = sys_get_temp_dir().'/neuron-codegen-guard-'.uniqid();
        File::ensureDirectoryExists($path);

        return $path;
    }

    public function test_master_off_disables_export_and_preview_even_when_children_true(): void
    {
        $this->setCodegen(enabled: false, export: true, preview: true);

        $this->assertFalse(CodegenGuard::enabled());
        $this->assertFalse(CodegenGuard::canExport());
        $this->assertFalse(CodegenGuard::canPreview());

        $this->expectException(CodegenDisabledException::class);
        CodegenGuard::ensureExport();
    }

    public function test_master_off_blocks_preview_even_when_preview_true(): void
    {
        $this->setCodegen(enabled: false, export: true, preview: true);

        $this->expectException(CodegenDisabledException::class);
        CodegenGuard::ensurePreview();
    }

    public function test_export_off_blocks_write_and_cli_but_preview_ok(): void
    {
        $exportPath = $this->exportPath();
        config([
            'neuronai-studio.export_path' => $exportPath,
            'neuronai-studio.export_namespace' => 'App\\Neuron',
        ]);
        $this->setCodegen(enabled: true, export: false, preview: true);

        $this->assertTrue(CodegenGuard::canPreview());
        $this->assertFalse(CodegenGuard::canExport());

        $workflow = WorkflowDefinition::create([
            'name' => 'Preview Only',
            'slug' => 'preview-only',
            'graph' => WorkflowDefinition::defaultGraph(),
            'status' => 'draft',
            'source' => 'studio',
        ]);

        $preview = app(WorkflowExporter::class)->preview($workflow);
        $this->assertNotEmpty($preview);

        $agent = AgentDefinition::create([
            'name' => 'Blocked Agent',
            'slug' => 'blocked-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
        ]);

        try {
            app(AgentExporter::class)->export($agent);
            $this->fail('Expected CodegenDisabledException for agent export.');
        } catch (CodegenDisabledException) {
            // expected
        }

        $this->artisan('neuronai-studio:export', ['type' => 'agent', 'id' => $agent->id])
            ->assertFailed();

        $this->artisan('neuronai-studio:make-tool', ['name' => 'Blocked'])
            ->assertFailed();

        File::deleteDirectory($exportPath);
    }

    public function test_preview_off_blocks_preview_but_export_ok(): void
    {
        $exportPath = $this->exportPath();
        config([
            'neuronai-studio.export_path' => $exportPath,
            'neuronai-studio.export_namespace' => 'App\\Neuron',
        ]);
        $this->setCodegen(enabled: true, export: true, preview: false);

        $this->assertTrue(CodegenGuard::canExport());
        $this->assertFalse(CodegenGuard::canPreview());

        $workflow = WorkflowDefinition::create([
            'name' => 'Export Only',
            'slug' => 'export-only',
            'graph' => WorkflowDefinition::defaultGraph(),
            'status' => 'draft',
            'source' => 'studio',
        ]);

        try {
            app(WorkflowExporter::class)->preview($workflow);
            $this->fail('Expected CodegenDisabledException for workflow preview.');
        } catch (CodegenDisabledException) {
            // expected
        }

        $files = app(WorkflowExporter::class)->export($workflow);
        $this->assertNotEmpty($files);
        $this->assertFileExists($files[0]);

        File::deleteDirectory($exportPath);
    }

    public function test_save_builder_with_export_off_persists_db_without_file_put(): void
    {
        $exportPath = $this->exportPath();
        config([
            'neuronai-studio.export_path' => $exportPath,
            'neuronai-studio.export_namespace' => 'App\\Neuron',
        ]);
        $this->setCodegen(enabled: true, export: false, preview: true);

        $existing = ToolDefinition::create([
            'name' => 'Existing Tool',
            'slug' => 'existing-tool',
            'type' => 'builder',
            'description' => 'Keep class path',
            'input_schema' => [
                ['name' => 'example', 'type' => 'string', 'description' => 'Example', 'required' => true],
            ],
            'config' => [
                'tool_name' => 'existing_tool',
                'class_name' => 'ExistingTool',
                'class_path' => 'App\\Neuron\\Tools\\ExistingTool',
                'invoke_body' => "return 'old';",
            ],
        ]);

        Livewire::test(Edit::class, ['tool' => $existing])
            ->set('toolKind', 'builder')
            ->set('name', 'Existing Tool Updated')
            ->set('toolName', 'existing_tool')
            ->set('description', 'Updated description')
            ->set('invokeBody', "return 'new';")
            ->set('inputSchema', [
                ['name' => 'example', 'type' => 'string', 'description' => 'Example', 'required' => true],
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $existing->refresh();

        $this->assertSame('Existing Tool Updated', $existing->name);
        $this->assertSame('Updated description', $existing->description);
        $this->assertSame("return 'new';", $existing->config['invoke_body']);
        $this->assertSame('App\\Neuron\\Tools\\ExistingTool', $existing->config['class_path']);
        $this->assertFalse(File::exists($exportPath.'/Tools/ExistingTool.php'));
        $this->assertSame([], File::files($exportPath) ?: []);

        File::deleteDirectory($exportPath);
    }

    public function test_tool_exporter_respects_ensure_export(): void
    {
        $this->setCodegen(enabled: true, export: false, preview: true);

        $tool = ToolDefinition::create([
            'name' => 'No Export',
            'slug' => 'no-export',
            'type' => 'builder',
            'description' => 'Blocked',
            'input_schema' => [],
            'config' => [
                'tool_name' => 'no_export',
                'class_name' => 'NoExportTool',
                'invoke_body' => "return 'x';",
            ],
        ]);

        $this->expectException(CodegenDisabledException::class);
        app(ToolExporter::class)->export($tool);
    }
}
