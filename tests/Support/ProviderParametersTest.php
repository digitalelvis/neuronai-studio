<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Support;

use DigitalElvis\NeuronAIStudio\Support\ProviderParameters;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class ProviderParametersTest extends TestCase
{
    public function test_gemini_maps_thinking_config(): void
    {
        $normalized = ProviderParameters::normalize('gemini', [
            'thinking_level' => 'LOW',
            'thinking_budget' => 512,
            'temperature' => 0.4,
        ]);

        $this->assertSame([
            'generationConfig' => [
                'temperature' => 0.4,
                'thinkingConfig' => [
                    'thinkingLevel' => 'LOW',
                    'thinkingBudget' => 512,
                ],
            ],
        ], $normalized);
    }
}
