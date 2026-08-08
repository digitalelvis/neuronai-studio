<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use Carbon\Carbon;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use NeuronAI\Testing\FakeAIProvider;
use ReflectionMethod;

class AgentRunnerDatetimePlaceholdersTest extends TestCase
{
    public function test_make_agent_resolves_studio_datetime_in_instructions(): void
    {
        config(['app.timezone' => 'UTC', 'app.locale' => 'en']);
        Carbon::setTestNow(Carbon::parse('2026-03-01 09:15:00', 'UTC'));

        $provider = new FakeAIProvider;
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($provider);

        $toolResolver = $this->createMock(ToolResolver::class);
        $toolResolver->method('resolveMany')->willReturn([]);

        $runner = new AgentRunner(
            $registry,
            $toolResolver,
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );

        $definition = AgentDefinition::create([
            'name' => 'Clock Bot',
            'slug' => 'clock-bot',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Clock {{__studio_now}} {{__studio_timezone}} {{__studio_locale}}',
        ]);

        $makeAgent = new ReflectionMethod(AgentRunner::class, 'makeAgent');
        $agent = $makeAgent->invoke($runner, $definition, [
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Clock {{__studio_now}} {{__studio_timezone}} {{__studio_locale}}',
            'tools' => [],
        ], null);

        $instructions = $agent->resolveInstructions();

        $this->assertSame('Clock 2026-03-01T09:15:00+00:00 UTC en', $instructions);
        $this->assertStringNotContainsString('{{', $instructions);

        Carbon::setTestNow();
    }
}
