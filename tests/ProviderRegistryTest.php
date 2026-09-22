<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use NeuronAI\Providers\Gemini\Gemini;
use ReflectionProperty;

class ProviderRegistryTest extends TestCase
{
    public function test_resolve_uses_requested_model_instead_of_config_default(): void
    {
        config([
            'neuron.provider.gemini' => [
                'key' => 'test-gemini-key',
                'model' => 'gemini-3-pro-preview',
                'parameters' => [],
            ],
        ]);

        $provider = app(ProviderRegistry::class)->resolve('gemini', 'gemini-3.5-flash');

        $this->assertInstanceOf(Gemini::class, $provider);

        $model = (new ReflectionProperty(Gemini::class, 'model'))->getValue($provider);

        $this->assertSame('gemini-3.5-flash', $model);
    }

    public function test_resolve_without_model_uses_config_default(): void
    {
        $provider = app(ProviderRegistry::class)->resolve('openai');

        $this->assertSame('gpt-4o-mini', (new ReflectionProperty($provider::class, 'model'))->getValue($provider));
    }

    public function test_chat_catalog_keeps_the_existing_first_model_of_each_provider(): void
    {
        $this->assertSame('gpt-4o', config('neuronai-studio.providers.openai.models')[0]);
        $this->assertSame('claude-sonnet-4-20250514', config('neuronai-studio.providers.anthropic.models')[0]);
        $this->assertSame('gemini-3.5-flash', config('neuronai-studio.providers.gemini.models')[0]);
        $this->assertSame('llama3.2', config('neuronai-studio.providers.ollama.models')[0]);
        $this->assertContains('gpt-6-astra', config('neuronai-studio.providers.openai.models'));
        $this->assertContains('claude-fable-5-1', config('neuronai-studio.providers.anthropic.models'));
        $this->assertArrayNotHasKey('gemini-3.1-pro-preview-customtools', array_flip(config('neuronai-studio.providers.gemini.models')));
    }

    public function test_resolve_throws_when_provider_key_is_missing(): void
    {
        config([
            'neuron.provider.openai' => [
                'key' => null,
                'model' => 'gpt-4o-mini',
                'parameters' => [],
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('OPENAI_KEY');

        app(ProviderRegistry::class)->resolve('openai', 'gpt-4o-mini');
    }
}
