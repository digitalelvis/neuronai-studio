<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\StructuredOutputValidationException;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\LlmNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\NodeExecutorRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\StructuredOutput\StructuredOutputResolver;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use DigitalElvis\NeuronAIStudio\Tests\Fixtures\Output\SampleLeadProfile;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;

class StructuredOutputWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $fixturesPath = __DIR__.'/Fixtures';

        config([
            'neuronai-studio.export_path' => $fixturesPath,
            'neuronai-studio.export_namespace' => 'DigitalElvis\\NeuronAIStudio\\Tests\\Fixtures',
            'neuronai-studio.structured_output_scan_paths' => [$fixturesPath.'/Output'],
        ]);
    }

    public function test_structured_llm_routes_true_branch_by_nested_condition_field(): void
    {
        $this->bindFakeProvider(
            new AssistantMessage('{"email": "alice@example.com", "tier": "gold"}'),
        );

        $workflow = WorkflowDefinition::create([
            'name' => 'Structured Output True Flow',
            'slug' => 'structured-output-true-flow',
            'graph' => $this->structuredConditionGraph(),
        ]);

        $trace = app(WorkflowRunner::class)->run($workflow, [
            'message' => 'Extract lead profile',
        ]);

        $this->assertEquals('completed', $trace->status);
        $this->assertEquals('true_branch', $trace->output['branch'] ?? null);
        $this->assertSame([
            'email' => 'alice@example.com',
            'tier' => 'gold',
        ], $trace->output['lead'] ?? null);
    }

    public function test_structured_llm_routes_false_branch_when_nested_field_mismatch(): void
    {
        $this->bindFakeProvider(
            new AssistantMessage('{"email": "bob@example.com", "tier": "silver"}'),
        );

        $workflow = WorkflowDefinition::create([
            'name' => 'Structured Output False Flow',
            'slug' => 'structured-output-false-flow',
            'graph' => $this->structuredConditionGraph(),
        ]);

        $trace = app(WorkflowRunner::class)->run($workflow, [
            'message' => 'Extract lead profile',
        ]);

        $this->assertEquals('completed', $trace->status);
        $this->assertEquals('false_branch', $trace->output['branch'] ?? null);
    }

    public function test_validation_failure_marks_trace_failed_and_emits_validation_errors(): void
    {
        $this->bindFakeProvider(
            new AssistantMessage('invalid json'),
            new AssistantMessage('still invalid json'),
        );

        $workflow = WorkflowDefinition::create([
            'name' => 'Structured Output Validation Failure',
            'slug' => 'structured-output-validation-failure',
            'graph' => $this->structuredConditionGraph(),
        ]);

        $events = [];

        try {
            app(WorkflowRunner::class)->run($workflow, [
                'message' => 'Extract lead profile',
            ], function (string $event, array $data) use (&$events): void {
                $events[] = ['event' => $event, 'data' => $data];
            });

            $this->fail('Expected StructuredOutputValidationException was not thrown.');
        } catch (StructuredOutputValidationException) {
            // expected
        }

        $failedStep = collect($events)->first(
            fn (array $event): bool => $event['event'] === 'step_completed'
                && ($event['data']['failed'] ?? false) === true,
        );

        $this->assertNotNull($failedStep);
        $this->assertNotEmpty($failedStep['data']['validation_errors']);
        $this->assertSame('failed', $failedStep['data']['handle']);

        $trace = $workflow->traces()->latest('id')->first();
        $this->assertNotNull($trace);
        $this->assertEquals('failed', $trace->status);
    }

    /** @return array<string, mixed> */
    protected function structuredConditionGraph(): array
    {
        return [
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'llm_1', 'type' => 'llm', 'position' => ['x' => 200, 'y' => 0], 'data' => [
                    'prompt' => 'Extract lead profile from: $input',
                    'provider' => 'openai',
                    'model' => 'gpt-4o-mini',
                    'output_key' => 'lead',
                    'structured' => true,
                    'output_class' => SampleLeadProfile::class,
                ]],
                ['id' => 'cond_1', 'type' => 'condition', 'position' => ['x' => 400, 'y' => 0], 'data' => [
                    'state_key' => 'lead.tier',
                    'operator' => 'equals',
                    'value' => 'gold',
                ]],
                ['id' => 'set_true', 'type' => 'set_state', 'position' => ['x' => 600, 'y' => -50], 'data' => [
                    'key' => 'branch',
                    'value' => 'true_branch',
                ]],
                ['id' => 'set_false', 'type' => 'set_state', 'position' => ['x' => 600, 'y' => 50], 'data' => [
                    'key' => 'branch',
                    'value' => 'false_branch',
                ]],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 800, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'llm_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e2', 'source' => 'llm_1', 'target' => 'cond_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e3', 'source' => 'cond_1', 'target' => 'set_true', 'sourceHandle' => 'true', 'targetHandle' => 'default'],
                ['id' => 'e4', 'source' => 'cond_1', 'target' => 'set_false', 'sourceHandle' => 'false', 'targetHandle' => 'default'],
                ['id' => 'e5', 'source' => 'set_true', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e6', 'source' => 'set_false', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
            ],
        ];
    }

    protected function bindFakeProvider(AssistantMessage ...$responses): void
    {
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn(new FakeAIProvider(...$responses));

        $toolResolver = $this->createMock(ToolResolver::class);
        $toolResolver->method('resolveMany')->willReturn([]);

        $runner = new AgentRunner(
            $registry,
            $toolResolver,
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );

        $this->app->instance(ProviderRegistry::class, $registry);
        $this->app->instance(AgentRunner::class, $runner);
        $this->app->make(NodeExecutorRegistry::class)->register(
            'llm',
            new LlmNodeExecutor(
                $registry,
                $runner,
                new MessageFactory,
                app(StructuredOutputResolver::class),
            ),
        );
    }
}
