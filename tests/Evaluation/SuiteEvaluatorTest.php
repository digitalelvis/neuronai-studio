<?php

namespace ElvisLopesDigital\NeuronAIStudio\Tests\Evaluation;

use ElvisLopesDigital\NeuronAIStudio\Evaluation\SuiteEvaluator;
use ElvisLopesDigital\NeuronAIStudio\Evaluation\ToolWasCalled;
use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Models\EvalSuite;
use ElvisLopesDigital\NeuronAIStudio\Registry\ProviderRegistry;
use ElvisLopesDigital\NeuronAIStudio\Runtime\AgentRunner;
use ElvisLopesDigital\NeuronAIStudio\Runtime\McpToolResolver;
use ElvisLopesDigital\NeuronAIStudio\Runtime\MessageFactory;
use ElvisLopesDigital\NeuronAIStudio\Runtime\ToolEventExtractor;
use ElvisLopesDigital\NeuronAIStudio\Runtime\ToolResolver;
use ElvisLopesDigital\NeuronAIStudio\Tests\TestCase;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Evaluation\Runner\EvaluatorRunner;
use NeuronAI\Testing\FakeAIProvider;

class SuiteEvaluatorTest extends TestCase
{
    public function test_suite_evaluator_runs_with_fake_provider(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Support Assistant',
            'slug' => 'support-assistant',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        $suite = EvalSuite::create([
            'agent_definition_id' => $agent->id,
            'name' => 'Basic',
            'slug' => 'basic',
            'dataset' => [
                [
                    'input' => 'Hello',
                    'reference' => 'fake',
                ],
            ],
        ]);

        $this->bindFakeProvider(new AssistantMessage('Eval fake response'));

        $summary = (new EvaluatorRunner)->run(new SuiteEvaluator($suite));

        $this->assertSame(1, $summary->getTotalCount());
        $this->assertSame(1, $summary->getPassedCount());
        $this->assertSame(0, $summary->getFailedCount());
        $this->assertSame('Eval fake response', $summary->getResults()[0]->getOutput());
    }

    public function test_suite_evaluator_fails_when_reference_not_found(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Support Assistant',
            'slug' => 'support-assistant-2',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        $suite = EvalSuite::create([
            'agent_definition_id' => $agent->id,
            'name' => 'Failing',
            'slug' => 'failing',
            'dataset' => [
                [
                    'input' => 'Hello',
                    'reference' => 'missing-keyword',
                ],
            ],
        ]);

        $this->bindFakeProvider(new AssistantMessage('Eval fake response'));

        $summary = (new EvaluatorRunner)->run(new SuiteEvaluator($suite));

        $this->assertSame(1, $summary->getFailedCount());
        $this->assertTrue($summary->getResults()[0]->hasAssertionFailures());
    }

    public function test_suite_evaluator_supports_contains_any_assertion(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Support Assistant',
            'slug' => 'support-assistant-3',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        $suite = EvalSuite::create([
            'agent_definition_id' => $agent->id,
            'name' => 'Assertions',
            'slug' => 'assertions',
            'dataset' => [
                [
                    'input' => 'Hours?',
                    '_assertions' => [
                        ['type' => 'contains_any', 'values' => ['fake', 'hours']],
                    ],
                ],
            ],
        ]);

        $this->bindFakeProvider(new AssistantMessage('Eval fake response'));

        $summary = (new EvaluatorRunner)->run(new SuiteEvaluator($suite));

        $this->assertSame(1, $summary->getPassedCount());
    }

    protected function bindFakeProvider(AssistantMessage $message): void
    {
        $provider = new FakeAIProvider($message);

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($provider);

        $this->app->instance(ProviderRegistry::class, $registry);
        $this->app->instance(AgentRunner::class, new AgentRunner(
            $registry,
            $this->createMock(ToolResolver::class),
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        ));
    }
}
