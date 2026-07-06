<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Integration\StreamAdapterRegistry;
use InvalidArgumentException;
use NeuronAI\Chat\Messages\Stream\Adapters\AGUIAdapter;
use NeuronAI\Chat\Messages\Stream\Adapters\VercelAIAdapter;

class StreamAdapterRegistryTest extends TestCase
{
    protected function registry(): StreamAdapterRegistry
    {
        return $this->app->make(StreamAdapterRegistry::class);
    }

    public function test_available_lists_enabled_adapters(): void
    {
        $available = $this->registry()->available();

        $this->assertArrayHasKey('vercel', $available);
        $this->assertArrayHasKey('agui', $available);
        $this->assertSame('available', $available['vercel']['status']);
        $this->assertSame('available', $available['agui']['status']);
    }

    public function test_roadmap_lists_catalog_only_protocols(): void
    {
        $roadmap = $this->registry()->roadmap();

        foreach (['openai-sse', 'anthropic-sse', 'langchain', 'copilotkit', 'websocket', 'inertia', 'ndjson'] as $protocol) {
            $this->assertArrayHasKey($protocol, $roadmap);
            $this->assertSame('roadmap', $roadmap[$protocol]['status']);
        }

        $this->assertArrayNotHasKey('vercel', $roadmap);
        $this->assertArrayNotHasKey('agui', $roadmap);
    }

    public function test_resolve_returns_concrete_adapters(): void
    {
        $registry = $this->registry();

        $this->assertInstanceOf(VercelAIAdapter::class, $registry->resolve('vercel'));
        $this->assertInstanceOf(AGUIAdapter::class, $registry->resolve('agui'));
    }

    public function test_resolve_throws_for_unknown_protocol(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registry()->resolve('does-not-exist');
    }

    public function test_disabled_protocol_is_not_available_and_cannot_resolve(): void
    {
        config(['neuronai-studio.stream_adapters.protocols.agui.enabled' => false]);

        $registry = $this->registry();

        $this->assertArrayNotHasKey('agui', $registry->available());
        $this->assertFalse($registry->isEnabled('agui'));

        $this->expectException(InvalidArgumentException::class);
        $registry->resolve('agui');
    }
}
