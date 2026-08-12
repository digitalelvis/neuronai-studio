<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
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

    public function test_accepts_run_workflow_step_mode_graph(): void
    {
        $child = WorkflowDefinition::create([
            'name' => 'Child Flow',
            'slug' => 'child-flow-'.uniqid(),
            'status' => 'draft',
            'source' => 'studio',
        ]);

        $validator = app(GraphValidator::class);
        $result = $validator->validate($this->runWorkflowStepGraph($child->id));

        $this->assertTrue($result['valid'], implode(' ', $result['errors']));
    }

    public function test_accepts_run_workflow_tool_mode_graph(): void
    {
        $child = WorkflowDefinition::create([
            'name' => 'Pricing Flow',
            'slug' => 'pricing-flow-'.uniqid(),
            'status' => 'draft',
            'source' => 'studio',
        ]);

        $validator = app(GraphValidator::class);
        $result = $validator->validate($this->runWorkflowToolModeGraph($child->id));

        $this->assertTrue($result['valid'], implode(' ', $result['errors']));
    }

    public function test_rejects_run_workflow_missing_workflow_id(): void
    {
        $graph = $this->runWorkflowStepGraph(1);
        $graph['nodes'][1]['data']['workflow_id'] = '';

        $validator = app(GraphValidator::class);
        $result = $validator->validate($graph);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('workflow_id', strtolower(implode(' ', $result['errors'])));
    }

    public function test_rejects_run_workflow_unknown_target(): void
    {
        $validator = app(GraphValidator::class);
        $result = $validator->validate($this->runWorkflowStepGraph(999999));

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('unknown workflow_id', strtolower(implode(' ', $result['errors'])));
    }

    public function test_rejects_run_workflow_self_reference(): void
    {
        $parent = WorkflowDefinition::create([
            'name' => 'Parent Flow',
            'slug' => 'parent-flow-'.uniqid(),
            'status' => 'draft',
            'source' => 'studio',
        ]);

        $validator = app(GraphValidator::class);
        $result = $validator->validate($this->runWorkflowStepGraph($parent->id), $parent->id);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('self-reference', strtolower(implode(' ', $result['errors'])));
    }

    public function test_rejects_run_workflow_empty_state_map_key(): void
    {
        $child = WorkflowDefinition::create([
            'name' => 'Child Flow',
            'slug' => 'child-map-'.uniqid(),
            'status' => 'draft',
            'source' => 'studio',
        ]);

        $graph = $this->runWorkflowStepGraph($child->id);
        $graph['nodes'][1]['data']['state_map'] = [
            ['key' => '', 'value' => '{{x}}'],
        ];

        $validator = app(GraphValidator::class);
        $result = $validator->validate($graph);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('empty state_map key', strtolower(implode(' ', $result['errors'])));
    }

    public function test_rejects_intent_classifier_with_fewer_than_two_intents(): void
    {
        $validator = app(GraphValidator::class);
        $result = $validator->validate([
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                [
                    'id' => 'ic_1',
                    'type' => 'intent_classifier',
                    'position' => ['x' => 100, 'y' => 0],
                    'data' => [
                        'intents' => [
                            ['id' => 'only_one', 'name' => 'Only', 'description' => 'One'],
                        ],
                    ],
                ],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'ic_1', 'sourceHandle' => 'default'],
                ['id' => 'e2', 'source' => 'ic_1', 'target' => 'stop_1', 'sourceHandle' => 'only_one'],
            ],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('at least two intents', implode(' ', $result['errors']));
    }

    public function test_rejects_intent_classifier_duplicate_ids(): void
    {
        $validator = app(GraphValidator::class);
        $result = $validator->validate([
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                [
                    'id' => 'ic_1',
                    'type' => 'intent_classifier',
                    'position' => ['x' => 100, 'y' => 0],
                    'data' => [
                        'intents' => [
                            ['id' => 'billing', 'name' => 'Billing', 'description' => 'A'],
                            ['id' => 'billing', 'name' => 'Billing 2', 'description' => 'B'],
                        ],
                    ],
                ],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'ic_1', 'sourceHandle' => 'default'],
                ['id' => 'e2', 'source' => 'ic_1', 'target' => 'stop_1', 'sourceHandle' => 'billing'],
            ],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('duplicate intent id', strtolower(implode(' ', $result['errors'])));
    }

    public function test_accepts_valid_intent_classifier_graph(): void
    {
        $validator = app(GraphValidator::class);
        $result = $validator->validate([
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                [
                    'id' => 'ic_1',
                    'type' => 'intent_classifier',
                    'position' => ['x' => 100, 'y' => 0],
                    'data' => [
                        'intents' => [
                            ['id' => 'billing', 'name' => 'Billing', 'description' => 'Payment'],
                            ['id' => 'other', 'name' => 'Other', 'description' => 'Fallback'],
                        ],
                    ],
                ],
                ['id' => 'agent_1', 'type' => 'agent', 'position' => ['x' => 200, 'y' => -40], 'data' => [
                    'config_mode' => 'inline',
                    'provider' => 'openai',
                    'model' => 'gpt-4o-mini',
                ]],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 300, 'y' => 0], 'data' => []],
                ['id' => 'stop_2', 'type' => 'stop', 'position' => ['x' => 300, 'y' => 80], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'ic_1', 'sourceHandle' => 'default'],
                ['id' => 'e2', 'source' => 'ic_1', 'target' => 'agent_1', 'sourceHandle' => 'billing'],
                ['id' => 'e3', 'source' => 'ic_1', 'target' => 'stop_2', 'sourceHandle' => 'other'],
                ['id' => 'e4', 'source' => 'agent_1', 'target' => 'stop_1', 'sourceHandle' => 'default'],
            ],
        ]);

        $this->assertTrue($result['valid'], implode(' ', $result['errors']));
    }

    public function test_rejects_switch_without_cases(): void
    {
        $validator = app(GraphValidator::class);
        $result = $validator->validate([
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'switch_1', 'type' => 'switch', 'position' => ['x' => 100, 'y' => 0], 'data' => ['cases' => []]],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'switch_1', 'sourceHandle' => 'default'],
                ['id' => 'e2', 'source' => 'switch_1', 'target' => 'stop_1', 'sourceHandle' => 'default'],
            ],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('requires at least one case', implode(' ', $result['errors']));
    }

    public function test_accepts_valid_switch_graph(): void
    {
        $validator = app(GraphValidator::class);
        $result = $validator->validate([
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                [
                    'id' => 'switch_1',
                    'type' => 'switch',
                    'position' => ['x' => 100, 'y' => 0],
                    'data' => [
                        'cases' => [
                            [
                                'id' => 'gold',
                                'label' => 'Gold',
                                'state_key' => 'tier',
                                'operator' => 'equals',
                                'value' => 'gold',
                            ],
                        ],
                    ],
                ],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 200, 'y' => -40], 'data' => []],
                ['id' => 'stop_2', 'type' => 'stop', 'position' => ['x' => 200, 'y' => 40], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'switch_1', 'sourceHandle' => 'default'],
                ['id' => 'e2', 'source' => 'switch_1', 'target' => 'stop_1', 'sourceHandle' => 'gold'],
                ['id' => 'e3', 'source' => 'switch_1', 'target' => 'stop_2', 'sourceHandle' => 'default'],
            ],
        ]);

        $this->assertTrue($result['valid'], implode(' ', $result['errors']));
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

    /**
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    protected function runWorkflowStepGraph(int $workflowId): array
    {
        return [
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                [
                    'id' => 'run_1',
                    'type' => 'run_workflow',
                    'position' => ['x' => 100, 'y' => 0],
                    'data' => [
                        'workflow_id' => (string) $workflowId,
                        'message' => '{{input}}',
                        'state_map' => [
                            ['key' => 'lead_id', 'value' => '{{lead_id}}'],
                        ],
                        'output_key' => 'child_output',
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
    }

    /**
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    protected function runWorkflowToolModeGraph(int $workflowId): array
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
                    'id' => 'run_tool_1',
                    'type' => 'run_workflow',
                    'position' => ['x' => 100, 'y' => 120],
                    'data' => [
                        'workflow_id' => (string) $workflowId,
                        'message' => '{{input}}',
                        'state_map' => [],
                        'output_key' => 'child_output',
                        'tool_mode' => true,
                        'tool_exposure' => [
                            'slug' => 'run_pricing_flow',
                            'description' => 'Run the pricing workflow.',
                        ],
                    ],
                ],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'supervisor_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e2', 'source' => 'supervisor_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e3', 'source' => 'run_tool_1', 'target' => 'supervisor_1', 'sourceHandle' => 'toolset', 'targetHandle' => 'tools'],
            ],
        ];
    }
}
