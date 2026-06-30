<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
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
}
