<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Registry\ToolRegistry;

class ToolRegistryTest extends TestCase
{
    public function test_all_includes_configured_toolkits(): void
    {
        config([
            'neuronai-studio.tools' => [
                'calculator' => [
                    'type' => 'toolkit',
                    'class' => \NeuronAI\Tools\Toolkits\Calculator\CalculatorToolkit::class,
                    'label' => 'Calculator',
                    'category' => 'builtin',
                ],
            ],
            'neuronai-studio.tool_scan_paths' => [],
            'neuronai-studio.mcp_servers' => [],
        ]);

        $entries = app(ToolRegistry::class)->all();

        $this->assertNotEmpty($entries);
        $this->assertSame('toolkit:calculator', $entries[0]['ref']);
        $this->assertSame('Calculator', $entries[0]['label']);
    }

    public function test_schema_inspector_enriches_toolkit_actions(): void
    {
        config([
            'neuronai-studio.tools' => [
                'calculator' => [
                    'type' => 'toolkit',
                    'class' => \NeuronAI\Tools\Toolkits\Calculator\CalculatorToolkit::class,
                    'label' => 'Calculator',
                    'category' => 'builtin',
                    'description' => 'Math toolkit',
                ],
            ],
            'neuronai-studio.tool_scan_paths' => [],
            'neuronai-studio.mcp_servers' => [],
        ]);

        $entry = app(ToolRegistry::class)->all()[0];
        $enriched = app(\DigitalElvis\NeuronAIStudio\Support\ToolSchemaInspector::class)->enrich($entry);

        $this->assertFalse($enriched['editable']);
        $this->assertNotEmpty($enriched['actions']);
        $this->assertSame('sum', $enriched['actions'][0]['name']);
        $this->assertNotEmpty($enriched['actions'][0]['properties']);
    }

    public function test_config_for_toolkit_ref(): void
    {
        config([
            'neuronai-studio.tools.calculator' => [
                'class' => \NeuronAI\Tools\Toolkits\Calculator\CalculatorToolkit::class,
            ],
        ]);

        $config = app(ToolRegistry::class)->configFor('toolkit:calculator');

        $this->assertSame(
            \NeuronAI\Tools\Toolkits\Calculator\CalculatorToolkit::class,
            $config['class']
        );
    }

    public function test_config_for_mcp_ref(): void
    {
        config([
            'neuronai-studio.mcp_servers.demo' => [
                'label' => 'Demo MCP',
                'connector' => ['command' => 'demo'],
            ],
        ]);

        $config = app(ToolRegistry::class)->configFor('mcp:demo');

        $this->assertSame('demo', $config['connector']['command']);
    }
}
