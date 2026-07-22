<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Codegen;

use DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators\CodegenContext;
use DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators\InvokeNodeCodeGenerator;
use DigitalElvis\NeuronAIStudio\Codegen\PhpArrayExporter;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class InvokeNodeCodegenTest extends TestCase
{
    public function test_emits_app_call_to_hook_class(): void
    {
        $generator = new InvokeNodeCodeGenerator;
        $context = new CodegenContext(new PhpArrayExporter);

        $result = $generator->generate([
            'data' => [
                'hook_class' => 'App\\Neuron\\Hooks\\EnrichLead',
                'output_key' => 'lead',
            ],
            'returnType' => 'default',
        ], $context);

        $this->assertStringContainsString('invoke_hooks', $result['body']);
        $this->assertStringContainsString('\\App\\Neuron\\Hooks\\EnrichLead::class', $result['body']);
        $this->assertStringContainsString("\$state->set('lead', app(\\App\\Neuron\\Hooks\\EnrichLead::class)(\$state));", $result['body']);
    }

    public function test_emits_exception_when_hook_class_missing(): void
    {
        $generator = new InvokeNodeCodeGenerator;
        $context = new CodegenContext(new PhpArrayExporter);

        $result = $generator->generate([
            'data' => [],
            'returnType' => 'default',
        ], $context);

        $this->assertStringContainsString('requires data.hook_class', $result['body']);
    }
}
