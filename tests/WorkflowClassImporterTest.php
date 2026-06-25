<?php

namespace ElvisLopesDigital\NeuronAIStudio\Tests;

use ElvisLopesDigital\NeuronAIStudio\Codegen\WorkflowClassImporter;
use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowDefinition;
use ElvisLopesDigital\NeuronAIStudio\Tests\Fixtures\NativeNeuronWorkflow;
use ElvisLopesDigital\NeuronAIStudio\Tests\Fixtures\SampleStudioWorkflow;

class WorkflowClassImporterTest extends TestCase
{
    public function test_imports_studio_workflow_class(): void
    {
        $result = app(WorkflowClassImporter::class)->fromClass(SampleStudioWorkflow::class);

        $this->assertNotNull($result);
        $this->assertFalse(app(WorkflowClassImporter::class)->hasError($result));
        $this->assertSame('Sample Workflow', $result['name']);
        $this->assertArrayHasKey('set_1', collect($result['graph']['nodes'])->keyBy('id')->all());
    }

    public function test_rejects_native_neuron_workflow_with_friendly_error(): void
    {
        $result = app(WorkflowClassImporter::class)->fromClass(NativeNeuronWorkflow::class);

        $this->assertTrue(app(WorkflowClassImporter::class)->hasError($result));
        $this->assertStringContainsString('native Workflow format', $result['error']);
    }

    public function test_imports_json_file(): void
    {
        $path = sys_get_temp_dir().'/demo-workflow-'.uniqid().'.json';
        $graph = WorkflowDefinition::defaultGraph();

        file_put_contents($path, json_encode([
            'meta' => ['name' => 'JSON Workflow'],
            'graph' => $graph,
        ], JSON_THROW_ON_ERROR));

        $result = app(WorkflowClassImporter::class)->fromJsonFile($path);

        @unlink($path);

        $this->assertFalse(app(WorkflowClassImporter::class)->hasError($result));
        $this->assertSame('JSON Workflow', $result['name']);
    }
}
