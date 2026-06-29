<?php

namespace ElvisLopesDigital\NeuronAIStudio\Tests\Evaluation;

use ElvisLopesDigital\NeuronAIStudio\Evaluation\SuiteEvaluator;
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
use NeuronAI\Evaluation\Assertions\AgentJudge;
use NeuronAI\Evaluation\Assertions\Judges\CorrectnessJudge;
use NeuronAI\Evaluation\Assertions\Judges\FaithfulnessJudge;
use NeuronAI\Evaluation\Assertions\Judges\HelpfulnessJudge;
use NeuronAI\Evaluation\Assertions\Judges\RelevanceJudge;
use NeuronAI\Evaluation\Runner\EvaluatorRunner;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Testing\FakeAIProvider;
use ReflectionMethod;

class SuiteEvaluatorTest extends TestCase
{
    public function test_suite_evaluator_runs_with_fake_provider(): void
    {
        $agent = $this->createAgent('support-assistant');

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

        $summary = (new EvaluatorRunner)->run(new SuiteEvaluator($suite, fakeAgentProvider: true));

        $this->assertSame(1, $summary->getTotalCount());
        $this->assertSame(1, $summary->getPassedCount());
        $this->assertSame(0, $summary->getFailedCount());
        $this->assertSame('Eval fake response', $summary->getResults()[0]->getOutput());
    }

    public function test_suite_evaluator_fails_when_reference_not_found(): void
    {
        $agent = $this->createAgent('support-assistant-2');

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

        $summary = (new EvaluatorRunner)->run(new SuiteEvaluator($suite, fakeAgentProvider: true));

        $this->assertSame(1, $summary->getFailedCount());
        $this->assertTrue($summary->getResults()[0]->hasAssertionFailures());
    }

    public function test_suite_evaluator_supports_contains_any_assertion(): void
    {
        $agent = $this->createAgent('support-assistant-3');

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

        $summary = (new EvaluatorRunner)->run(new SuiteEvaluator($suite, fakeAgentProvider: true));

        $this->assertSame(1, $summary->getPassedCount());
    }

    public function test_dataset_requires_judge_detects_judge_assertions(): void
    {
        $this->assertFalse(SuiteEvaluator::datasetRequiresJudge([
            ['input' => 'Hi', 'reference' => 'Hi'],
        ]));

        $this->assertTrue(SuiteEvaluator::datasetRequiresJudge([
            [
                'input' => 'Hi',
                '_assertions' => [
                    ['type' => 'helpfulness'],
                ],
            ],
        ]));
    }

    public function test_judge_assertion_without_judge_agent_records_error(): void
    {
        $agent = $this->createAgent('support-assistant-4');

        $suite = EvalSuite::create([
            'agent_definition_id' => $agent->id,
            'name' => 'Judge Missing',
            'slug' => 'judge-missing',
            'dataset' => [
                [
                    'input' => 'Hello',
                    '_assertions' => [
                        ['type' => 'helpfulness'],
                    ],
                ],
            ],
        ]);

        $summary = (new EvaluatorRunner)->run(new SuiteEvaluator($suite, fakeAgentProvider: true));

        $this->assertSame('Judge assertions require a judge agent on this eval suite.', $summary->getResults()[0]->getError());
    }

    public function test_build_assertion_maps_judge_types(): void
    {
        $agent = $this->createAgent('tested-agent');
        $judgeAgent = $this->createAgent('judge-agent', 'You evaluate.');

        $suite = EvalSuite::create([
            'agent_definition_id' => $agent->id,
            'judge_agent_definition_id' => $judgeAgent->id,
            'name' => 'Judge Types',
            'slug' => 'judge-types',
            'dataset' => [],
        ]);

        $evaluator = new SuiteEvaluator($suite);
        $evaluator->setUp();

        $method = new ReflectionMethod(SuiteEvaluator::class, 'buildAssertion');
        $method->setAccessible(true);

        $case = ['input' => 'What are your hours?', 'context' => 'Open 9-5'];

        $this->assertInstanceOf(CorrectnessJudge::class, $method->invoke($evaluator, ['type' => 'correctness', 'expected' => '9-5'], $case));
        $this->assertInstanceOf(FaithfulnessJudge::class, $method->invoke($evaluator, ['type' => 'faithfulness'], $case));
        $this->assertInstanceOf(RelevanceJudge::class, $method->invoke($evaluator, ['type' => 'relevance'], $case));
        $this->assertInstanceOf(HelpfulnessJudge::class, $method->invoke($evaluator, ['type' => 'helpfulness'], $case));
        $this->assertInstanceOf(AgentJudge::class, $method->invoke($evaluator, ['type' => 'criteria', 'criteria' => 'Be polite'], $case));
    }

    public function test_judge_resolved_from_judge_agent_definition(): void
    {
        $agent = $this->createAgent('tested-agent-2');
        $judgeAgent = $this->createAgent('judge-agent-2', 'You are a strict judge.');

        $suite = EvalSuite::create([
            'agent_definition_id' => $agent->id,
            'judge_agent_definition_id' => $judgeAgent->id,
            'name' => 'With Judge',
            'slug' => 'with-judge',
            'dataset' => [],
        ]);

        $evaluator = new SuiteEvaluator($suite);
        $evaluator->setUp();

        $method = new ReflectionMethod(SuiteEvaluator::class, 'resolveJudge');
        $method->setAccessible(true);

        $judge = $method->invoke($evaluator);

        $this->assertNotNull($judge);
    }

    public function test_fake_agent_provider_does_not_use_registry_for_tested_agent(): void
    {
        $agent = $this->createAgent('fake-scope-agent');

        $suite = EvalSuite::create([
            'agent_definition_id' => $agent->id,
            'name' => 'Fake Scope',
            'slug' => 'fake-scope',
            'dataset' => [
                ['input' => 'Hello', 'reference' => 'fake'],
            ],
        ]);

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->expects($this->never())->method('resolve');

        $this->app->instance(ProviderRegistry::class, $registry);
        $this->app->instance(AgentRunner::class, new AgentRunner(
            $registry,
            $this->createMock(ToolResolver::class),
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        ));

        $summary = (new EvaluatorRunner)->run(new SuiteEvaluator($suite, fakeAgentProvider: true));

        $this->assertSame(1, $summary->getPassedCount());
    }

    public function test_judge_agent_still_uses_registry_when_fake_enabled_for_tested_agent(): void
    {
        $agent = $this->createAgent('fake-scope-agent-2');
        $judgeAgent = $this->createAgent('fake-scope-judge', 'Judge instructions.');

        $suite = EvalSuite::create([
            'agent_definition_id' => $agent->id,
            'judge_agent_definition_id' => $judgeAgent->id,
            'name' => 'Fake Scope Judge',
            'slug' => 'fake-scope-judge',
            'dataset' => [
                ['input' => 'Hello', 'reference' => 'fake'],
            ],
        ]);

        $resolveCount = 0;

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturnCallback(function () use (&$resolveCount) {
            $resolveCount++;

            return new FakeAIProvider(new AssistantMessage('unused'));
        });

        $this->app->instance(ProviderRegistry::class, $registry);
        $this->app->instance(AgentRunner::class, new AgentRunner(
            $registry,
            $this->createMock(ToolResolver::class),
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        ));

        (new EvaluatorRunner)->run(new SuiteEvaluator($suite, fakeAgentProvider: true));

        $this->assertSame(1, $resolveCount);
    }

    protected function createAgent(string $slug, string $instructions = 'You are helpful.'): AgentDefinition
    {
        return AgentDefinition::create([
            'name' => $slug,
            'slug' => $slug,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => $instructions,
        ]);
    }
}
