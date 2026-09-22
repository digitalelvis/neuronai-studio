<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Codegen;

use DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators\CodegenContext;
use DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators\MediaNodeCodeGenerator;
use DigitalElvis\NeuronAIStudio\Codegen\PhpArrayExporter;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class MediaNodeCodegenTest extends TestCase
{
    public function test_image_node_delegates_to_the_media_executor(): void
    {
        $result = (new MediaNodeCodeGenerator)->generate([
            'type' => 'image',
            'data' => [
                'provider' => 'gemini',
                'model' => 'gemini-3.1-flash-image',
                'prompt' => '{{input}}',
                'output_key' => 'image_result',
            ],
            'returnType' => 'DefaultEvent',
        ], new CodegenContext(new PhpArrayExporter));

        $this->assertStringContainsString('MediaNodeExecutor', $result['body']);
        $this->assertStringContainsString('gemini-3.1-flash-image', $result['body']);
        $this->assertStringContainsString('return new DefaultEvent();', $result['body']);
    }
}
