<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Support\PlaygroundContext;
use DigitalElvis\NeuronAIStudio\Support\ProviderParameters;

class PlaygroundContextTest extends TestCase
{
    public function test_augment_instructions_appends_context_json(): void
    {
        $result = PlaygroundContext::augmentInstructions('You are helpful.', [
            'plan' => 'gold',
            'user_id' => 'test',
        ]);

        $this->assertStringContainsString('You are helpful.', $result);
        $this->assertStringContainsString('"plan": "gold"', $result);
        $this->assertStringContainsString('"user_id": "test"', $result);
    }

    public function test_normalize_extracts_state_wrapper(): void
    {
        $this->assertSame(
            ['plan' => 'gold'],
            PlaygroundContext::normalize(['state' => ['plan' => 'gold'], 'threadId' => 'abc']),
        );
    }

    public function test_provider_parameters_map_gemini_generation_config(): void
    {
        $mapped = ProviderParameters::normalize('gemini', [
            'temperature' => 0.4,
            'top_p' => 0.9,
            'max_tokens' => 512,
        ]);

        $this->assertSame(0.4, $mapped['generationConfig']['temperature']);
        $this->assertSame(0.9, $mapped['generationConfig']['topP']);
        $this->assertSame(512, $mapped['generationConfig']['maxOutputTokens']);
    }
}
