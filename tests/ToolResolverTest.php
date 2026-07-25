<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\ToolDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Tools\IssueRefundTool;
use NeuronAI\Tools\Toolkits\Calculator\CalculatorToolkit;
use NeuronAI\Tools\Toolkits\ToolkitInterface;

class ToolResolverTest extends TestCase
{
    public function test_resolve_toolkit_binding(): void
    {
        config([
            'neuronai-studio.tools.calculator' => [
                'class' => CalculatorToolkit::class,
            ],
        ]);

        $resolved = app(ToolResolver::class)->resolve('toolkit:calculator');

        $this->assertCount(1, $resolved);
        $this->assertInstanceOf(ToolkitInterface::class, $resolved[0]);
    }

    public function test_resolve_many_flattens_bindings(): void
    {
        config([
            'neuronai-studio.tools.calculator' => [
                'class' => CalculatorToolkit::class,
            ],
        ]);

        $resolved = app(ToolResolver::class)->resolveMany([
            ['ref' => 'toolkit:calculator'],
        ]);

        $this->assertCount(1, $resolved);
    }

    public function test_builder_without_class_path_fails_closed(): void
    {
        $tool = ToolDefinition::create([
            'name' => 'No Export Yet',
            'slug' => 'no-export-yet',
            'type' => 'builder',
            'description' => 'Body only',
            'input_schema' => [],
            'config' => [
                'tool_name' => 'no_export_yet',
                'invoke_body' => "return 'x';",
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('has no exported class_path');

        app(ToolResolver::class)->resolve($tool->bindingRef());
    }

    public function test_builder_with_class_path_resolves_when_builder_tools_disabled(): void
    {
        config(['neuronai-studio.allow_builder_tools' => false]);

        $tool = ToolDefinition::create([
            'name' => 'Exported Builder',
            'slug' => 'exported-builder',
            'type' => 'builder',
            'description' => 'Already exported',
            'input_schema' => [],
            'config' => [
                'tool_name' => 'exported_builder',
                'class_path' => IssueRefundTool::class,
                'invoke_body' => "return 'unused';",
            ],
        ]);

        $resolved = app(ToolResolver::class)->resolve($tool->bindingRef());

        $this->assertCount(1, $resolved);
        $this->assertInstanceOf(IssueRefundTool::class, $resolved[0]);
    }
}
