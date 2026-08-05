<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Codegen;

use DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators\CodegenContext;
use DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators\IntentClassifierNodeCodeGenerator;
use DigitalElvis\NeuronAIStudio\Codegen\PhpArrayExporter;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class IntentClassifierNodeCodegenTest extends TestCase
{
    public function test_emits_structured_classify_and_branch_returns(): void
    {
        $generator = new IntentClassifierNodeCodeGenerator;
        $context = new CodegenContext(new PhpArrayExporter);

        $result = $generator->generate([
            'data' => [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'message' => '{{input}}',
                'output_key' => 'intent',
                'vision' => false,
                'memory' => false,
                'intents' => [
                    ['id' => 'billing', 'name' => 'Billing', 'description' => 'Payment questions'],
                    ['id' => 'other', 'name' => 'Other', 'description' => 'Fallback'],
                ],
            ],
            'returnType' => 'BillingEvent|OtherEvent',
            'branchReturns' => [
                'billing' => 'BillingEvent',
                'other' => 'OtherEvent',
            ],
        ], $context);

        $this->assertStringContainsString('IntentClassifierNodeExecutor::normalizeIntents', $result['body']);
        $this->assertStringContainsString('structuredInline(', $result['body']);
        $this->assertStringContainsString('IntentClassificationResult::class', $result['body']);
        $this->assertStringContainsString("return new BillingEvent();", $result['body']);
        $this->assertStringContainsString("return new OtherEvent();", $result['body']);
        $this->assertStringContainsString("resolveAttachmentsForNode", $result['body']);
        $this->assertContains(
            'DigitalElvis\\NeuronAIStudio\\Runtime\\StructuredOutput\\IntentClassificationResult',
            $result['imports'],
        );
    }
}
