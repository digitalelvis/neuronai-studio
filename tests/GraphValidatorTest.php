<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Runtime\GraphValidator;
use DigitalElvis\NeuronAIStudio\Tests\Fixtures\InvokeTestHook;

class GraphValidatorTest extends TestCase
{
    public function test_validates_default_graph(): void
    {
        $validator = app(GraphValidator::class);
        $result = $validator->validate([
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
            ],
        ]);

        $this->assertTrue($result['valid']);
    }

    public function test_rejects_graph_without_start(): void
    {
        $validator = app(GraphValidator::class);
        $result = $validator->validate([
            'nodes' => [
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
            ],
            'edges' => [],
        ]);

        $this->assertFalse($result['valid']);
    }

    public function test_rejects_unauthorized_cycle(): void
    {
        $validator = app(GraphValidator::class);
        $result = $validator->validate([
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'set_1', 'type' => 'set_state', 'position' => ['x' => 100, 'y' => 0], 'data' => []],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'set_1', 'sourceHandle' => 'default'],
                ['id' => 'e2', 'source' => 'set_1', 'target' => 'set_1', 'sourceHandle' => 'default'],
                ['id' => 'e3', 'source' => 'set_1', 'target' => 'stop_1', 'sourceHandle' => 'default'],
            ],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('loop node', strtolower(implode(' ', $result['errors'])));
    }

    public function test_accepts_graph_with_authorized_loop_cycle(): void
    {
        $validator = app(GraphValidator::class);
        $result = $validator->validate([
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'loop_1', 'type' => 'loop', 'position' => ['x' => 100, 'y' => 0], 'data' => ['max_steps' => 3]],
                ['id' => 'set_1', 'type' => 'set_state', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 300, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'loop_1', 'sourceHandle' => 'default'],
                ['id' => 'e2', 'source' => 'loop_1', 'target' => 'set_1', 'sourceHandle' => 'continue'],
                ['id' => 'e3', 'source' => 'set_1', 'target' => 'loop_1', 'sourceHandle' => 'default'],
                ['id' => 'e4', 'source' => 'loop_1', 'target' => 'stop_1', 'sourceHandle' => 'exit'],
            ],
        ]);

        $this->assertTrue($result['valid'], implode(' ', $result['errors']));
    }

    public function test_rejects_invoke_without_hook_class(): void
    {
        $validator = app(GraphValidator::class);
        $result = $validator->validate([
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'invoke_1', 'type' => 'invoke', 'position' => ['x' => 100, 'y' => 0], 'data' => []],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'invoke_1', 'sourceHandle' => 'default'],
                ['id' => 'e2', 'source' => 'invoke_1', 'target' => 'stop_1', 'sourceHandle' => 'default'],
            ],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('hook_class', implode(' ', $result['errors']));
    }

    public function test_rejects_invoke_outside_allowlist(): void
    {
        config(['neuronai-studio.invoke_hooks' => []]);

        $validator = app(GraphValidator::class);
        $result = $validator->validate([
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                [
                    'id' => 'invoke_1',
                    'type' => 'invoke',
                    'position' => ['x' => 100, 'y' => 0],
                    'data' => ['hook_class' => InvokeTestHook::class],
                ],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'invoke_1', 'sourceHandle' => 'default'],
                ['id' => 'e2', 'source' => 'invoke_1', 'target' => 'stop_1', 'sourceHandle' => 'default'],
            ],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('invoke_hooks', implode(' ', $result['errors']));
    }

    public function test_accepts_allowlisted_invoke_hook(): void
    {
        config(['neuronai-studio.invoke_hooks' => [InvokeTestHook::class]]);

        $validator = app(GraphValidator::class);
        $result = $validator->validate([
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                [
                    'id' => 'invoke_1',
                    'type' => 'invoke',
                    'position' => ['x' => 100, 'y' => 0],
                    'data' => ['hook_class' => InvokeTestHook::class],
                ],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'invoke_1', 'sourceHandle' => 'default'],
                ['id' => 'e2', 'source' => 'invoke_1', 'target' => 'stop_1', 'sourceHandle' => 'default'],
            ],
        ]);

        $this->assertTrue($result['valid'], implode(' ', $result['errors']));
    }

    public function test_accepts_inline_agent_with_tool_binding_edge(): void
    {
        $validator = app(GraphValidator::class);
        $result = $validator->validate([
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                [
                    'id' => 'agent_1',
                    'type' => 'agent',
                    'position' => ['x' => 100, 'y' => 0],
                    'data' => [
                        'config_mode' => 'inline',
                        'provider' => 'openai',
                        'model' => 'gpt-4o-mini',
                    ],
                ],
                [
                    'id' => 'tool_1',
                    'type' => 'tool',
                    'position' => ['x' => 100, 'y' => 120],
                    'data' => ['tool_ref' => 'toolkit:calculator'],
                ],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'agent_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e2', 'source' => 'agent_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e3', 'source' => 'tool_1', 'target' => 'agent_1', 'sourceHandle' => 'default', 'targetHandle' => 'tools'],
            ],
        ]);

        $this->assertTrue($result['valid'], implode(' ', $result['errors']));
    }

    public function test_rejects_tools_edge_from_non_tool_source(): void
    {
        $validator = app(GraphValidator::class);
        $result = $validator->validate([
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                [
                    'id' => 'agent_1',
                    'type' => 'agent',
                    'position' => ['x' => 100, 'y' => 0],
                    'data' => [
                        'config_mode' => 'inline',
                        'provider' => 'openai',
                        'model' => 'gpt-4o-mini',
                    ],
                ],
                ['id' => 'llm_1', 'type' => 'llm', 'position' => ['x' => 100, 'y' => 120], 'data' => []],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'agent_1', 'sourceHandle' => 'default'],
                ['id' => 'e2', 'source' => 'agent_1', 'target' => 'stop_1', 'sourceHandle' => 'default'],
                ['id' => 'e3', 'source' => 'llm_1', 'target' => 'agent_1', 'sourceHandle' => 'default', 'targetHandle' => 'tools'],
            ],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('tool or mcp', strtolower(implode(' ', $result['errors'])));
    }

    public function test_rejects_inline_agent_without_provider_model(): void
    {
        $validator = app(GraphValidator::class);
        $result = $validator->validate([
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                [
                    'id' => 'agent_1',
                    'type' => 'agent',
                    'position' => ['x' => 100, 'y' => 0],
                    'data' => ['config_mode' => 'inline'],
                ],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'agent_1', 'sourceHandle' => 'default'],
                ['id' => 'e2', 'source' => 'agent_1', 'target' => 'stop_1', 'sourceHandle' => 'default'],
            ],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('provider', strtolower(implode(' ', $result['errors'])));
    }

    public function test_accepts_supervisor_with_tool_mode_specialist(): void
    {
        $validator = app(GraphValidator::class);
        $result = $validator->validate($this->supervisorSpecialistGraph());

        $this->assertTrue($result['valid'], implode(' ', $result['errors']));
    }

    public function test_accepts_toolset_edge_on_existing_supervisor(): void
    {
        $graph = $this->supervisorSpecialistGraph();
        $graph['nodes'][1]['data'] = [
            'config_mode' => 'existing',
            'agent_id' => 42,
        ];

        $validator = app(GraphValidator::class);
        $result = $validator->validate($graph);

        $this->assertTrue($result['valid'], implode(' ', $result['errors']));
    }

    public function test_rejects_control_flow_edge_to_tool_mode_agent(): void
    {
        $graph = $this->supervisorSpecialistGraph();
        $graph['edges'][] = [
            'id' => 'e_cf',
            'source' => 'start_1',
            'target' => 'specialist_1',
            'sourceHandle' => 'default',
            'targetHandle' => 'default',
        ];

        $validator = app(GraphValidator::class);
        $result = $validator->validate($graph);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('tool mode', strtolower(implode(' ', $result['errors'])));
    }

    public function test_rejects_control_flow_edge_from_tool_mode_agent(): void
    {
        $graph = $this->supervisorSpecialistGraph();
        $graph['edges'][] = [
            'id' => 'e_cf',
            'source' => 'specialist_1',
            'target' => 'stop_1',
            'sourceHandle' => 'default',
            'targetHandle' => 'default',
        ];

        $validator = app(GraphValidator::class);
        $result = $validator->validate($graph);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('tool mode', strtolower(implode(' ', $result['errors'])));
    }

    public function test_rejects_duplicate_toolset_slug_on_same_supervisor(): void
    {
        $graph = $this->supervisorSpecialistGraph();
        $graph['nodes'][] = [
            'id' => 'specialist_2',
            'type' => 'agent',
            'position' => ['x' => 100, 'y' => 240],
            'data' => [
                'config_mode' => 'inline',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'tool_mode' => true,
                'tool_exposure' => [
                    'slug' => 'research_agent',
                    'description' => 'Second specialist',
                ],
            ],
        ];
        $graph['edges'][] = [
            'id' => 'e4',
            'source' => 'specialist_2',
            'target' => 'supervisor_1',
            'sourceHandle' => 'toolset',
            'targetHandle' => 'tools',
        ];

        $validator = app(GraphValidator::class);
        $result = $validator->validate($graph);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('slug', strtolower(implode(' ', $result['errors'])));
    }

    public function test_rejects_toolset_edge_from_agent_without_tool_mode(): void
    {
        $validator = app(GraphValidator::class);
        $result = $validator->validate([
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                [
                    'id' => 'supervisor_1',
                    'type' => 'agent',
                    'position' => ['x' => 100, 'y' => 0],
                    'data' => [
                        'config_mode' => 'inline',
                        'provider' => 'openai',
                        'model' => 'gpt-4o-mini',
                    ],
                ],
                [
                    'id' => 'agent_2',
                    'type' => 'agent',
                    'position' => ['x' => 100, 'y' => 120],
                    'data' => [
                        'config_mode' => 'inline',
                        'provider' => 'openai',
                        'model' => 'gpt-4o-mini',
                        'tool_mode' => false,
                    ],
                ],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'supervisor_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e2', 'source' => 'supervisor_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e3', 'source' => 'agent_2', 'target' => 'supervisor_1', 'sourceHandle' => 'toolset', 'targetHandle' => 'tools'],
            ],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('tool_mode', strtolower(implode(' ', $result['errors'])));
    }

    public function test_rejects_invalid_tool_exposure_slug(): void
    {
        $graph = $this->supervisorSpecialistGraph();
        $graph['nodes'][2]['data']['tool_exposure']['slug'] = 'bad-slug!';

        $validator = app(GraphValidator::class);
        $result = $validator->validate($graph);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('invalid tool_exposure.slug', strtolower(implode(' ', $result['errors'])));
    }

    /**
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    protected function supervisorSpecialistGraph(): array
    {
        return [
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                [
                    'id' => 'supervisor_1',
                    'type' => 'agent',
                    'position' => ['x' => 100, 'y' => 0],
                    'data' => [
                        'config_mode' => 'inline',
                        'provider' => 'openai',
                        'model' => 'gpt-4o-mini',
                    ],
                ],
                [
                    'id' => 'specialist_1',
                    'type' => 'agent',
                    'position' => ['x' => 100, 'y' => 120],
                    'data' => [
                        'config_mode' => 'inline',
                        'provider' => 'openai',
                        'model' => 'gpt-4o-mini',
                        'tool_mode' => true,
                        'tool_exposure' => [
                            'slug' => 'research_agent',
                            'description' => 'Research specialist',
                        ],
                    ],
                ],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'supervisor_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e2', 'source' => 'supervisor_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e3', 'source' => 'specialist_1', 'target' => 'supervisor_1', 'sourceHandle' => 'toolset', 'targetHandle' => 'tools'],
            ],
        ];
    }
}
