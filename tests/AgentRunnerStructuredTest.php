<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\StructuredOutputValidationException;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Tests\Fixtures\Output\SampleLeadProfile;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;

class AgentRunnerStructuredTest extends TestCase
{
    protected function fixtureScanConfig(): void
    {
        $fixturesPath = __DIR__.'/Fixtures';

        config([
            'neuronai-studio.export_path' => $fixturesPath,
            'neuronai-studio.export_namespace' => 'DigitalElvis\\NeuronAIStudio\\Tests\\Fixtures',
            'neuronai-studio.structured_output_scan_paths' => [$fixturesPath.'/Output'],
        ]);
    }

    protected function runnerWithProvider(FakeAIProvider $provider): AgentRunner
    {
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($provider);

        return new AgentRunner(
            $registry,
            $this->createMock(ToolResolver::class),
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );
    }

    public function test_structured_inline_returns_validated_array(): void
    {
        $this->fixtureScanConfig();

        $provider = new FakeAIProvider(
            new AssistantMessage('{"email": "alice@example.com", "tier": "gold"}'),
        );

        $runner = $this->runnerWithProvider($provider);

        $result = $runner->structuredInline([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Extract lead profile.',
        ], 'Alice is a gold tier lead at alice@example.com', SampleLeadProfile::class);

        $this->assertSame([
            'email' => 'alice@example.com',
            'tier' => 'gold',
        ], $result->structured);
        $this->assertSame('', $result->content);

        $provider->assertMethodCallCount('structured', 1);
    }

    public function test_structured_inline_throws_validation_exception_on_invalid_response(): void
    {
        $this->fixtureScanConfig();

        $provider = new FakeAIProvider(
            new AssistantMessage('not valid json'),
            new AssistantMessage('still not valid json'),
        );

        $runner = $this->runnerWithProvider($provider);

        try {
            $runner->structuredInline([
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'instructions' => 'Extract lead profile.',
            ], 'Hello', SampleLeadProfile::class);

            $this->fail('Expected StructuredOutputValidationException was not thrown.');
        } catch (StructuredOutputValidationException $exception) {
            $this->assertNotEmpty($exception->validationErrors);
        }
    }
}
