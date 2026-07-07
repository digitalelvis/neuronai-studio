<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use Illuminate\Routing\Middleware\SubstituteBindings;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;
use Orchestra\Testbench\Attributes\DefineEnvironment;

class AgentIntegrateStreamTest extends TestCase
{
    protected function fakeProvider(string $text = 'Hello integration world'): FakeAIProvider
    {
        $provider = new FakeAIProvider(new AssistantMessage($text));
        $provider->setStreamChunkSize(4);

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($provider);
        $this->app->instance(ProviderRegistry::class, $registry);

        return $provider;
    }

    protected function agent(): AgentDefinition
    {
        return AgentDefinition::create([
            'name' => 'Integration Agent',
            'slug' => 'integration-agent-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);
    }

    #[DefineEnvironment('lightIntegrationMiddleware')]
    public function test_vercel_protocol_streams_text_delta_with_header(): void
    {
        $this->fakeProvider('Hello integration world');
        $agent = $this->agent();

        $response = $this->postJson(
            route('neuronai-studio.integrate.agents.stream', ['agent' => $agent, 'protocol' => 'vercel']),
            ['message' => 'hi there'],
        );

        $response->assertOk();
        $response->assertHeader('x-vercel-ai-ui-message-stream', 'v1');

        $content = $response->streamedContent();
        $this->assertStringContainsString('"type":"text-delta"', $content);
        $this->assertStringContainsString('"type":"start"', $content);
        $this->assertStringContainsString('[DONE]', $content);
    }

    #[DefineEnvironment('lightIntegrationMiddleware')]
    public function test_agui_protocol_emits_run_started_and_finished(): void
    {
        $this->fakeProvider('Hello agui');
        $agent = $this->agent();

        $response = $this->postJson(
            route('neuronai-studio.integrate.agents.stream', ['agent' => $agent, 'protocol' => 'agui']),
            ['message' => 'hi there'],
        );

        $response->assertOk();

        $content = $response->streamedContent();
        $this->assertStringContainsString('RUN_STARTED', $content);
        $this->assertStringContainsString('TEXT_MESSAGE_CONTENT', $content);
        $this->assertStringContainsString('RUN_FINISHED', $content);
    }

    #[DefineEnvironment('lightIntegrationMiddleware')]
    public function test_unknown_protocol_returns_404(): void
    {
        $this->fakeProvider();
        $agent = $this->agent();

        $response = $this->postJson(
            route('neuronai-studio.integrate.agents.stream', ['agent' => $agent, 'protocol' => 'bogus']),
            ['message' => 'hi there'],
        );

        $response->assertNotFound();
    }

    protected function lightIntegrationMiddleware($app): void
    {
        $app['config']->set('neuronai-studio.stream_adapters.middleware', [SubstituteBindings::class]);
    }
}
