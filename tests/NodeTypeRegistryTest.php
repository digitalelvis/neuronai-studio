<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Registry\NodeTypeRegistry;

class NodeTypeRegistryTest extends TestCase
{
    public function test_for_canvas_exposes_toolable_meta_for_agent(): void
    {
        $canvas = app(NodeTypeRegistry::class)->forCanvas();

        $this->assertArrayHasKey('agent', $canvas);
        $this->assertTrue($canvas['agent']['toolable'] ?? false);
        $this->assertSame('call_agent', $canvas['agent']['tool_exposure']['slug_prefix'] ?? null);
        $this->assertNotEmpty($canvas['agent']['tool_exposure']['default_description'] ?? null);
    }

    public function test_for_canvas_exposes_toolable_meta_for_run_workflow(): void
    {
        $canvas = app(NodeTypeRegistry::class)->forCanvas();

        $this->assertArrayHasKey('run_workflow', $canvas);
        $this->assertTrue($canvas['run_workflow']['toolable'] ?? false);
        $this->assertSame('run_workflow', $canvas['run_workflow']['tool_exposure']['slug_prefix'] ?? null);
        $this->assertNotEmpty($canvas['run_workflow']['tool_exposure']['default_description'] ?? null);
        $this->assertSame('logic', $canvas['run_workflow']['category'] ?? null);
    }

    public function test_for_canvas_other_built_in_types_are_not_toolable(): void
    {
        $canvas = app(NodeTypeRegistry::class)->forCanvas();
        $toolableTypes = ['agent', 'run_workflow'];

        foreach ($canvas as $type => $meta) {
            if (in_array($type, $toolableTypes, true)) {
                continue;
            }

            $this->assertFalse(
                ($meta['toolable'] ?? false) === true,
                "Node type [{$type}] must omit toolable or set it false"
            );
        }
    }

    public function test_for_canvas_run_workflow_label_resolves_in_en_and_pt_br(): void
    {
        app()->setLocale('en');
        $en = app(NodeTypeRegistry::class)->forCanvas();
        $this->assertSame('Run Workflow', $en['run_workflow']['label'] ?? null);

        app()->setLocale('pt_BR');
        $pt = app(NodeTypeRegistry::class)->forCanvas();
        $this->assertSame('Executar Workflow', $pt['run_workflow']['label'] ?? null);
    }
}
