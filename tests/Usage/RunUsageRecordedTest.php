<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Usage;

use DigitalElvis\NeuronAIStudio\Events\RunUsageRecorded;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Testing\FakeAIProvider;

class RunUsageRecordedTest extends TestCase
{
    public function test_dispatches_run_usage_recorded_when_events_enabled(): void
    {
        config(['neuronai-studio.usage.events.enabled' => true]);
        Event::fake([RunUsageRecorded::class]);

        $agent = AgentDefinition::create([
            'name' => 'Event Agent',
            'slug' => 'event-agent-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        $runner = $this->makeAgentRunner(
            (new AssistantMessage('ok'))->setUsage(new Usage(10, 5)),
        );

        $result = $runner->run($agent, 'Hi');

        Event::assertDispatched(RunUsageRecorded::class, function (RunUsageRecorded $event) use ($result, $agent) {
            return $event->run->id === $result->runId
                && $event->promptTokens === 10
                && $event->completionTokens === 5
                && $event->totalTokens === 15
                && $event->entityType === 'agent'
                && (string) $event->entityId === (string) $agent->id
                && $event->parentRunId === null
                && $event->currency === 'USD';
        });
    }

    public function test_does_not_dispatch_when_events_disabled(): void
    {
        config(['neuronai-studio.usage.events.enabled' => false]);
        Event::fake([RunUsageRecorded::class]);

        $runner = $this->makeAgentRunner(
            (new AssistantMessage('ok'))->setUsage(new Usage(10, 5)),
        );

        $runner->runInline([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ], 'Hi');

        Event::assertNotDispatched(RunUsageRecorded::class);
    }

    protected function makeAgentRunner(AssistantMessage ...$responses): AgentRunner
    {
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn(new FakeAIProvider(...$responses));

        $toolResolver = $this->createMock(ToolResolver::class);
        $toolResolver->method('resolveMany')->willReturn([]);

        return new AgentRunner(
            $registry,
            $toolResolver,
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );
    }
}
