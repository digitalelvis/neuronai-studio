<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Codegen;

use DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators\CodegenContext;
use DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators\LlmNodeCodeGenerator;
use DigitalElvis\NeuronAIStudio\Codegen\PhpArrayExporter;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class LlmNodeCodegenTest extends TestCase
{
    public function test_chat_path_resolves_vault_api_key(): void
    {
        $generator = new LlmNodeCodeGenerator;
        $context = new CodegenContext(new PhpArrayExporter);

        $result = $generator->generate([
            'data' => [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => 'var:OPENAI_KEY',
                'prompt' => '{{input}}',
                'output_key' => 'llm_response',
            ],
            'returnType' => 'DefaultEvent',
        ], $context);

        $this->assertStringContainsString('ConfigValueResolver::class', $result['body']);
        $this->assertStringContainsString('var:OPENAI_KEY', $result['body']);
    }

    public function test_structured_path_includes_api_key(): void
    {
        $generator = new LlmNodeCodeGenerator;
        $context = new CodegenContext(new PhpArrayExporter);

        $result = $generator->generate([
            'data' => [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => 'var:OPENAI_KEY',
                'prompt' => '{{input}}',
                'structured' => true,
                'output_class' => 'App\\Neuron\\Outputs\\DemoOutput',
            ],
            'returnType' => 'DefaultEvent',
        ], $context);

        $this->assertStringContainsString("'api_key' => 'var:OPENAI_KEY'", $result['body']);
        $this->assertStringContainsString('structuredInline(', $result['body']);
    }
}
