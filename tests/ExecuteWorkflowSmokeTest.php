<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\GraphValidator;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\AgentNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\NodeExecutorRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\StructuredOutput\StructuredOutputResolver;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\Tools\WorkflowAsTool;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Testing\FakeAIProvider;

class ExecuteWorkflowSmokeTest extends TestCase
{
    protected function childGraphWithGreeting(string $greeting = 'Hello child'): array
    {
        return [
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'set_1', 'type' => 'set_state', 'position' => ['x' => 200, 'y' => 0], 'data' => ['key' => 'greeting', 'value' => $greeting]],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 400, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'set_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e2', 'source' => 'set_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
            ],
        ];
    }

    public function test_step_mode_parent_run_workflow_stop_nests_child(): void
    {
        $child = WorkflowDefinition::create([
            'name' => 'Smoke Child',
            'slug' => 'smoke-child-'.uniqid(),
            'graph' => $this->childGraphWithGreeting('Nested greeting'),
        ]);

        $parent = WorkflowDefinition::create([
            'name' => 'Smoke Parent Step',
            'slug' => 'smoke-parent-step-'.uniqid(),
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    [
                        'id' => 'run_1',
                        'type' => 'run_workflow',
                        'position' => ['x' => 160, 'y' => 0],
                        'data' => [
                            'workflow_id' => (string) $child->id,
                            'message' => '{{input}}',
                            'state_map' => [
                                ['key' => 'lead_id', 'value' => '{{lead_id}}'],
                            ],
                            'output_key' => 'child_output',
                            'tool_mode' => false,
                        ],
                    ],
                    ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 320, 'y' => 0], 'data' => []],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start_1', 'target' => 'run_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e2', 'source' => 'run_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ],
            ],
        ]);

        $run = app(WorkflowRunner::class)->run($parent, [
            'input' => 'parent-msg',
            'state' => ['lead_id' => '42'],
        ]);

        $this->assertSame('completed', $run->status);

        $written = $run->output['child_output'] ?? null;
        $this->assertIsString($written);
        $decoded = json_decode($written, true);
        $this->assertIsArray($decoded);
        $this->assertSame('Nested greeting', $decoded['greeting'] ?? null);
        $this->assertSame('parent-msg', $decoded['input'] ?? null);
        $this->assertSame('42', $decoded['lead_id'] ?? null);

        $nested = StudioRun::query()->where('parent_run_id', $run->id)->first();
        $this->assertNotNull($nested);
        $this->assertSame('completed', $nested->status);
        $this->assertSame(1, $nested->output[WorkflowRunner::NESTING_DEPTH_INPUT_KEY] ?? null);
    }

    public function test_tool_mode_supervisor_invokes_run_workflow_tool(): void
    {
        $child = WorkflowDefinition::create([
            'name' => 'Smoke Tool Child',
            'slug' => 'smoke-tool-child-'.uniqid(),
            'graph' => $this->childGraphWithGreeting('From tool child'),
        ]);

        $parent = WorkflowDefinition::create([
            'name' => 'Smoke Parent Tool',
            'slug' => 'smoke-parent-tool-'.uniqid(),
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    [
                        'id' => 'supervisor_1',
                        'type' => 'agent',
                        'position' => ['x' => 160, 'y' => 0],
                        'data' => [
                            'config_mode' => 'inline',
                            'provider' => 'openai',
                            'model' => 'gpt-4o-mini',
                            'instructions' => 'Call tools when needed.',
                            'output_key' => 'agent_response',
                        ],
                    ],
                    [
                        'id' => 'run_tool_1',
                        'type' => 'run_workflow',
                        'position' => ['x' => 160, 'y' => 120],
                        'data' => [
                            'workflow_id' => (string) $child->id,
                            'message' => 'default-msg',
                            'state_map' => [],
                            'tool_mode' => true,
                            'tool_exposure' => [
                                'slug' => 'run_pricing_flow',
                                'description' => 'Run the pricing workflow.',
                            ],
                        ],
                    ],
                    ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 320, 'y' => 0], 'data' => []],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start_1', 'target' => 'supervisor_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e2', 'source' => 'supervisor_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e3', 'source' => 'run_tool_1', 'target' => 'supervisor_1', 'sourceHandle' => 'toolset', 'targetHandle' => 'tools'],
                ],
            ],
        ]);

        $executableTool = new WorkflowAsTool(
            'run_pricing_flow',
            'Run the pricing workflow.',
            [
                'workflow_id' => (string) $child->id,
                'message' => 'default-msg',
                'state_map' => [],
            ],
        );
        $executableTool
            ->setInputs(['input' => 'Caller pricing task'])
            ->setCallId('call_rw_1');

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn(new FakeAIProvider(
            new ToolCallMessage(null, [$executableTool]),
            new AssistantMessage('Supervisor done'),
        ));

        $agentRunner = new AgentRunner(
            $registry,
            app(ToolResolver::class),
            app(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );
        $this->app->instance(AgentRunner::class, $agentRunner);
        $this->app->make(NodeExecutorRegistry::class)->register(
            'agent',
            new AgentNodeExecutor($agentRunner, new MessageFactory, app(StructuredOutputResolver::class)),
        );

        $run = app(WorkflowRunner::class)->run($parent, ['input' => 'Need pricing']);

        $this->assertSame('completed', $run->status);
        $this->assertSame('Supervisor done', $run->output['agent_response'] ?? null);

        $childRun = StudioRun::query()
            ->whereHas('thread', function ($query) use ($child) {
                $query->where('entity_type', WorkflowDefinition::class)
                    ->where('entity_id', $child->id);
            })
            ->latest('started_at')
            ->first();

        $this->assertNotNull($childRun);
        $this->assertSame('completed', $childRun->status);
        $this->assertSame('Caller pricing task', $childRun->output['input'] ?? null);
        $this->assertSame('From tool child', $childRun->output['greeting'] ?? null);
    }

    public function test_self_call_rejected_at_validate(): void
    {
        $parent = WorkflowDefinition::create([
            'name' => 'Self Call Parent',
            'slug' => 'self-call-parent-'.uniqid(),
            'status' => 'draft',
            'source' => 'studio',
        ]);

        $graph = [
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                [
                    'id' => 'run_1',
                    'type' => 'run_workflow',
                    'position' => ['x' => 100, 'y' => 0],
                    'data' => [
                        'workflow_id' => (string) $parent->id,
                        'message' => '{{input}}',
                        'tool_mode' => false,
                    ],
                ],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'run_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e2', 'source' => 'run_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
            ],
        ];

        $result = app(GraphValidator::class)->validate($graph, $parent->id);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('self-reference', strtolower(implode(' ', $result['errors'])));
    }
}
