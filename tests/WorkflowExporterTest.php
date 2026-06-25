<?php

namespace ElvisLopesDigital\NeuronAIStudio\Tests;

use ElvisLopesDigital\NeuronAIStudio\Codegen\WorkflowExporter;
use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowDefinition;

class WorkflowExporterTest extends TestCase
{
    public function test_exports_studio_workflow_class_with_graph(): void
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

        $files = app(WorkflowExporter::class)->export($workflow);

        $this->assertCount(1, $files);
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('implements StudioWorkflow', $content);
        $this->assertStringContainsString('studioGraph', $content);
        $this->assertStringContainsString("'start_1'", $content);
        $this->assertStringContainsString("'Atendimento'", $content);

        @unlink($files[0]);
        @rmdir($exportPath);
    }

    public function test_preview_returns_studio_workflow_class_without_writing_file(): void
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

        $content = app(WorkflowExporter::class)->preview($workflow);

        $this->assertStringContainsString('implements StudioWorkflow', $content);
        $this->assertStringContainsString('studioGraph', $content);
        $this->assertStringContainsString("'start_1'", $content);
        $this->assertStringContainsString("'Atendimento'", $content);
        $this->assertStringContainsString('class AtendimentoWorkflow', $content);
    }
}
