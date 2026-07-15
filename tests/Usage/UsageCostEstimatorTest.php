<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Usage;

use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use DigitalElvis\NeuronAIStudio\Usage\UsageCostEstimator;

class UsageCostEstimatorTest extends TestCase
{
    private UsageCostEstimator $estimator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estimator = new UsageCostEstimator;
    }

    public function test_currency_defaults_to_usd(): void
    {
        $this->assertSame('USD', $this->estimator->currency());
    }

    public function test_currency_reads_config_override(): void
    {
        config(['neuronai-studio.usage.currency' => 'BRL']);

        $this->assertSame('BRL', $this->estimator->currency());
    }

    public function test_estimate_priced_model(): void
    {
        config([
            'neuronai-studio.usage.pricing.openai.gpt-4o-mini' => [
                'prompt_per_1k' => 0.00015,
                'completion_per_1k' => 0.0006,
            ],
        ]);

        // (2000/1000)*0.00015 + (500/1000)*0.0006 = 0.0003 + 0.0003
        $this->assertSame('0.000600', $this->estimator->estimate('openai', 'gpt-4o-mini', 2000, 500));
    }

    public function test_estimate_unpriced_model_returns_zero(): void
    {
        $this->assertSame('0.000000', $this->estimator->estimate('openai', 'unknown-model', 1000, 1000));
    }

    public function test_estimate_null_provider_or_model_returns_zero(): void
    {
        $this->assertSame('0.000000', $this->estimator->estimate(null, 'gpt-4o-mini', 100, 100));
        $this->assertSame('0.000000', $this->estimator->estimate('openai', null, 100, 100));
        $this->assertSame('0.000000', $this->estimator->estimate('', '', 100, 100));
    }

    public function test_estimate_zero_tokens_returns_zero(): void
    {
        $this->assertSame('0.000000', $this->estimator->estimate('openai', 'gpt-4o-mini', 0, 0));
    }

    public function test_estimate_malformed_rates_coerce_to_zero(): void
    {
        config([
            'neuronai-studio.usage.pricing.openai.broken' => [
                'prompt_per_1k' => 'not-a-number',
                'completion_per_1k' => null,
            ],
        ]);

        $this->assertSame('0.000000', $this->estimator->estimate('openai', 'broken', 1000, 1000));
    }

    public function test_rate_returns_null_when_missing(): void
    {
        $this->assertNull($this->estimator->rate('openai', 'nope'));
        $this->assertNull($this->estimator->rate(null, null));
    }

    public function test_rate_returns_normalized_pair_for_catalog_model(): void
    {
        $rate = $this->estimator->rate('openai', 'gpt-4o-mini');

        $this->assertIsArray($rate);
        $this->assertSame(0.00015, $rate['prompt_per_1k']);
        $this->assertSame(0.0006, $rate['completion_per_1k']);
    }

    public function test_ollama_defaults_estimate_to_zero(): void
    {
        $this->assertSame('0.000000', $this->estimator->estimate('ollama', 'llama3.2', 5000, 5000));
    }
}
