<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\StructuredOutputValidationException;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\GraphExecutionLoop;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\LlmNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\NodeExecutorRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\StructuredOutput\StructuredOutputResolver;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Tests\Fixtures\Output\SampleLeadProfile;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;

class GraphExecutionLoopStructuredTest extends TestCase
{
    public function test_emits_step_completed_with_validation_errors_on_structured_failure(): void
    {
        $fixturesPath = __DIR__.'/Fixtures';

        config([
            'neuronai-studio.export_path' => $fixturesPath,
            'neuronai-studio.export_namespace' => 'DigitalElvis\\NeuronAIStudio\\Tests\\Fixtures',
            'neuronai-studio.structured_output_scan_paths' => [$fixturesPath.'/Output'],
        ]);

        $fakeProvider = new FakeAIProvider(
            new AssistantMessage('invalid json'),
            new AssistantMessage('still invalid json'),
        );
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($fakeProvider);

        $runner = new AgentRunner(
            $registry,
            $this->createMock(ToolResolver::class),
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );

        $executors = new NodeExecutorRegistry;
        $executors->register('llm', new LlmNodeExecutor(
            $registry,
            $runner,
            new MessageFactory,
            app(StructuredOutputResolver::class),
        ));

        $nodes = [
            ['id' => 'llm-1', 'type' => 'llm', 'data' => [
                'prompt' => 'Extract',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'output_key' => 'lead',
                'structured' => true,
                'output_class' => SampleLeadProfile::class,
            ]],
            ['id' => 'stop-1', 'type' => 'stop', 'data' => []],
        ];
        $edges = [
            ['source' => 'llm-1', 'target' => 'stop-1', 'sourceHandle' => 'default'],
        ];

        $context = new GraphContext($nodes, $edges);
        $state = new BuilderWorkflowState($context, null, []);
        $events = [];

        $state->stepEmitter = function (string $event, array $data) use (&$events): void {
            $events[] = ['event' => $event, 'data' => $data];
        };

        $loop = new GraphExecutionLoop($executors);

        try {
            $loop->runFromNode('llm-1', $context, $state);
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
    }
}
