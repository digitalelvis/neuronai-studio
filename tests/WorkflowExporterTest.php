<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Codegen\WorkflowExporter;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;

class WorkflowExporterTest extends TestCase
{
    public function test_exports_native_workflow_files(): void
    {
        $exportPath = sys_get_temp_dir().'/neuron-workflow-export-'.uniqid();
        mkdir($exportPath, 0777, true);

        config([
            'neuronai-studio.export_path' => $exportPath,
            'neuronai-studio.export_namespace' => 'App\\Neuron',
        ]);

        $workflow = WorkflowDefinition::create([
            'name' => 'Atendimento',
            'slug' => 'atendimento',
            'graph' => WorkflowDefinition::defaultGraph(),
            'status' => 'draft',
            'source' => 'studio',
        ]);

        $result = app(WorkflowExporter::class)->exportWithMeta($workflow);

        $this->assertNotEmpty($result['files']);
        $content = file_get_contents($result['files'][0]);

        $this->assertStringContainsString('extends Workflow', $content);
        $this->assertStringContainsString('StudioGraph', $content);
        $this->assertStringContainsString('Atendimento', $content);
        $this->assertStringNotContainsString('implements StudioWorkflow', $content);
        $this->assertStringNotContainsString('studioGraph()', $content);

        $this->cleanupExport($exportPath);
    }

    public function test_preview_meta_returns_native_code_without_writing(): void
    {
        config([
            'neuronai-studio.export_namespace' => 'App\\Neuron',
        ]);

        $workflow = WorkflowDefinition::make([
            'name' => 'Atendimento',
            'slug' => 'atendimento',
            'graph' => WorkflowDefinition::defaultGraph(),
            'status' => 'draft',
        ]);

        $meta = app(WorkflowExporter::class)->previewMeta($workflow);

        $this->assertStringContainsString('extends Workflow', $meta['code']);
        $this->assertStringContainsString('class AtendimentoWorkflow', $meta['code']);
        $this->assertSame('AtendimentoWorkflow', $meta['className']);
        $this->assertGreaterThan(0, $meta['fileCount']);
    }

    protected function cleanupExport(string $exportPath): void
    {
        if (! is_dir($exportPath)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($exportPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }

        @rmdir($exportPath);
    }
}
