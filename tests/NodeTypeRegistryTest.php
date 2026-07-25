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

    public function test_for_canvas_other_built_in_types_are_not_toolable(): void
    {
        $canvas = app(NodeTypeRegistry::class)->forCanvas();

        foreach ($canvas as $type => $meta) {
            if ($type === 'agent') {
                continue;
            }

            $this->assertFalse(
                ($meta['toolable'] ?? false) === true,
                "Node type [{$type}] must omit toolable or set it false"
            );
        }
    }
}
