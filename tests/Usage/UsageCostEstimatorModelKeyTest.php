<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Usage;

use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use DigitalElvis\NeuronAIStudio\Usage\UsageCostEstimator;

class UsageCostEstimatorModelKeyTest extends TestCase
{
    public function test_rate_resolves_models_with_dots_in_name(): void
    {
        config([
            'neuronai-studio.usage.pricing.gemini' => [
                'gemini-3.5-flash' => [
                    'prompt_per_1k' => 0.0015,
                    'completion_per_1k' => 0.009,
                ],
            ],
        ]);

        $rates = (new UsageCostEstimator)->rate('gemini', 'gemini-3.5-flash');

        $this->assertSame([
            'prompt_per_1k' => 0.0015,
            'completion_per_1k' => 0.009,
        ], $rates);
    }
}
