<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioChatMessage;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\GraphValidator;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\AgentNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\NodeExecutorRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use DigitalElvis\NeuronAIStudio\Services\TemplateInstaller;
use DigitalElvis\NeuronAIStudio\Support\ChatThreadKey;
use Illuminate\Support\Facades\Storage;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;

class AutonomousMultimodalAgentsTest extends TestCase
{
    public function test_autonomous_lead_qualification_template_is_valid(): void
    {
        $workflow = app(TemplateInstaller::class)->installWorkflow('autonomous-lead-qualification');
        $result = app(GraphValidator::class)->validate($workflow->graph);

        $this->assertTrue($result['valid'], implode(' ', $result['errors']));
        $this->assertTrue(
            collect($workflow->graph['nodes'] ?? [])->contains(fn (array $n) => ($n['type'] ?? '') === 'loop'),
        );
        $this->assertTrue(
            collect($workflow->graph['nodes'] ?? [])->contains(fn (array $n) => ($n['type'] ?? '') === 'agent'),
        );
    }

    public function test_agent_node_emits_tool_events_during_workflow_run(): void
    {
        $events = [];
        $agent = AgentDefinition::create([
            'name' => 'Tool Agent',
            'slug' => 'tool-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Use tools.',
            'tools' => [['ref' => 'toolkit:calculator', 'config' => []]],
        ]);

        $workflow = WorkflowDefinition::create([
            'name' => 'Tool Event Flow',
            'slug' => 'tool-event-flow',
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    ['id' => 'agent_1', 'type' => 'agent', 'position' => ['x' => 200, 'y' => 0], 'data' => [
                        'agent_id' => $agent->id,
                        'output_key' => 'agent_response',
                    ]],
                    ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 400, 'y' => 0], 'data' => []],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start_1', 'target' => 'agent_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e2', 'source' => 'agent_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ],
            ],
        ]);

        $this->bindFakeAgentRunnerWithToolEvents(
            new AssistantMessage('Done'),
            [
                ['name' => 'calculator', 'inputs' => ['expression' => '2+2'], 'result' => '4', 'type' => 'result'],
            ],
        );

        app(WorkflowRunner::class)->run($workflow, ['message' => 'Calculate'], function (string $event, array $data) use (&$events) {
            $events[] = $event;
        });

        $this->assertContains('tool_result', $events);
    }

    public function test_loop_agent_run_preserves_thread_and_attachments(): void
    {
        Storage::fake('local');
        config(['neuronai-studio.attachments.disk' => 'local']);

        $storageKey = 'neuronai-studio/attachments/test.jpg';
        Storage::disk('local')->put(
            $storageKey,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
        );

        $agent = AgentDefinition::create([
            'name' => 'Loop Vision Agent',
            'slug' => 'loop-vision-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'instructions' => 'Extract lead email from input.',
        ]);

        $workflow = WorkflowDefinition::create([
            'name' => 'Loop Agent Multimodal',
            'slug' => 'loop-agent-multimodal',
            'graph' => $this->loopAgentGraph($agent->id),
        ]);

        $threadId = '550e8400-e29b-41d4-a716-446655440000';
        $scopedKey = ChatThreadKey::forWorkflow($workflow->id, $threadId);

        $this->bindFakeAgentRunner(
            new AssistantMessage('Name: Jane\nEmail: jane@example.com'),
            new AssistantMessage('Follow up'),
        );

        $trace = app(WorkflowRunner::class)->run($workflow, [
            'message' => 'Qualify this lead',
            'thread_id' => $threadId,
            'attachments' => [
                [
                    'type' => 'image',
                    'storage_key' => $storageKey,
                    'mime_type' => 'image/png',
                    'name' => 'lead-card.png',
                ],
            ],
        ]);

        $this->assertEquals('completed', $trace->status);
        $this->assertSame($scopedKey, $trace->output['__studio_thread_id'] ?? null);
        $this->assertIsArray($trace->output['attachments'] ?? null);
        $this->assertStringContainsString('@', (string) ($trace->output['lead_profile'] ?? ''));
        $this->assertGreaterThanOrEqual(2, StudioChatMessage::query()->where('thread_id', $scopedKey)->count());
    }

    public function test_agent_node_executor_emits_tool_events_to_state(): void
    {
        $emitted = [];
        $agent = AgentDefinition::create([
            'name' => 'Emit Agent',
            'slug' => 'emit-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Help.',
        ]);

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn(new FakeAIProvider(new AssistantMessage('ok')));

        $agentRunner = $this->createMock(AgentRunner::class);
        $agentRunner->method('runInline')->willReturn(new \DigitalElvis\NeuronAIStudio\Runtime\AgentRunResult(
            'ok',
            [['name' => 'calculator', 'inputs' => ['expression' => '1+1'], 'result' => '2', 'type' => 'call']],
        ));

        $executor = new AgentNodeExecutor($agentRunner, new MessageFactory);
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, 1, []);
        $state->stepEmitter = function (string $event, array $data) use (&$emitted) {
            $emitted[] = ['event' => $event, 'data' => $data];
        };

        $executor->execute([
            'id' => 'agent_1',
            'data' => [
                'agent_id' => $agent->id,
                'output_key' => 'agent_response',
            ],
        ], $state, $context);

        $this->assertSame('ok', $state->get('agent_response'));
        $this->assertCount(1, $emitted);
        $this->assertSame('tool_call', $emitted[0]['event']);
        $this->assertSame('agent_1', $emitted[0]['data']['node_id']);
    }

    /** @return array<string, mixed> */
    protected function loopAgentGraph(int $agentId): array
    {
        return [
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'loop_1', 'type' => 'loop', 'position' => ['x' => 200, 'y' => 0], 'data' => [
                    'max_steps' => 3,
                    'state_key' => 'lead_profile',
                    'operator' => 'contains',
                    'value' => '@',
                ]],
                ['id' => 'agent_1', 'type' => 'agent', 'position' => ['x' => 400, 'y' => 100], 'data' => [
                    'agent_id' => $agentId,
                    'message' => 'Extract email from: {{input}}',
                    'output_key' => 'lead_profile',
                ]],
                ['id' => 'set_done', 'type' => 'set_state', 'position' => ['x' => 400, 'y' => 0], 'data' => [
                    'key' => 'result',
                    'value' => 'qualified',
                ]],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 600, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'loop_1', 'sourceHandle' => 'default'],
                ['id' => 'e2', 'source' => 'loop_1', 'target' => 'agent_1', 'sourceHandle' => 'continue'],
                ['id' => 'e3', 'source' => 'agent_1', 'target' => 'loop_1', 'sourceHandle' => 'default'],
                ['id' => 'e4', 'source' => 'loop_1', 'target' => 'set_done', 'sourceHandle' => 'exit'],
                ['id' => 'e5', 'source' => 'set_done', 'target' => 'stop_1', 'sourceHandle' => 'default'],
            ],
        ];
    }

    protected function bindFakeAgentRunner(AssistantMessage ...$responses): AgentRunner
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

        $this->app->instance(AgentRunner::class, $runner);
        $this->app->make(NodeExecutorRegistry::class)->register(
            'agent',
            new AgentNodeExecutor($runner, new MessageFactory),
        );

        return $runner;
    }

    /**
     * @param  array<int, array{name: string, inputs: array<string, mixed>, result: string|null, type: string}>  $toolEvents
     */
    protected function bindFakeAgentRunnerWithToolEvents(AssistantMessage $response, array $toolEvents): AgentRunner
    {
        $agentRunner = $this->createMock(AgentRunner::class);
        $agentRunner->method('runInline')->willReturn(new \DigitalElvis\NeuronAIStudio\Runtime\AgentRunResult(
            $response->getContent(),
            $toolEvents,
        ));

        $this->app->instance(AgentRunner::class, $agentRunner);
        $this->app->make(NodeExecutorRegistry::class)->register(
            'agent',
            new AgentNodeExecutor($agentRunner, new MessageFactory),
        );

        return $agentRunner;
    }
}
