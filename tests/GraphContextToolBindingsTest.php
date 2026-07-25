<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;

class GraphContextToolBindingsTest extends TestCase
{
    public function test_resolves_tool_and_mcp_bindings_from_tools_edges(): void
    {
        $context = new GraphContext(
            [
                [
                    'id' => 'agent_1',
                    'type' => 'agent',
                    'data' => ['config_mode' => 'inline'],
                ],
                [
                    'id' => 'tool_1',
                    'type' => 'tool',
                    'data' => ['tool_ref' => 'toolkit:calculator'],
                ],
                [
                    'id' => 'mcp_1',
                    'type' => 'mcp',
                    'data' => ['mcp_server' => 'filesystem', 'tool_name' => 'read_file'],
                ],
                [
                    'id' => 'llm_1',
                    'type' => 'llm',
                    'data' => [],
                ],
            ],
            [
                ['source' => 'tool_1', 'target' => 'agent_1', 'sourceHandle' => 'default', 'targetHandle' => 'tools'],
                ['source' => 'mcp_1', 'target' => 'agent_1', 'sourceHandle' => 'default', 'targetHandle' => 'tools'],
                ['source' => 'llm_1', 'target' => 'agent_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
            ],
        );

        $bindings = $context->toolBindingsFor('agent_1');

        $this->assertSame(
            [
                ['ref' => 'toolkit:calculator'],
                ['ref' => 'mcp:filesystem', 'only' => ['read_file']],
            ],
            $bindings,
        );
    }

    public function test_target_for_handle_skips_tools_binding_edges(): void
    {
        $context = new GraphContext(
            [
                ['id' => 'tool_1', 'type' => 'tool', 'data' => []],
                ['id' => 'agent_1', 'type' => 'agent', 'data' => []],
                ['id' => 'stop_1', 'type' => 'stop', 'data' => []],
            ],
            [
                ['source' => 'tool_1', 'target' => 'agent_1', 'sourceHandle' => 'default', 'targetHandle' => 'tools'],
                ['source' => 'tool_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
            ],
        );

        $this->assertSame('stop_1', $context->targetForHandle('tool_1'));
    }

    public function test_resolves_toolset_binding_as_node_ref_with_exposure(): void
    {
        $context = new GraphContext(
            [
                [
                    'id' => 'supervisor_1',
                    'type' => 'agent',
                    'data' => ['config_mode' => 'inline'],
                ],
                [
                    'id' => 'specialist_1',
                    'type' => 'agent',
                    'data' => [
                        'tool_mode' => true,
                        'tool_exposure' => [
                            'slug' => 'research_agent',
                            'description' => 'Research specialist',
                            'parameters' => [
                                'input' => [
                                    'controlled_by' => 'caller',
                                    'description' => 'Task for the specialist',
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'tool_1',
                    'type' => 'tool',
                    'data' => ['tool_ref' => 'toolkit:calculator'],
                ],
            ],
            [
                ['source' => 'specialist_1', 'target' => 'supervisor_1', 'sourceHandle' => 'toolset', 'targetHandle' => 'tools'],
                ['source' => 'tool_1', 'target' => 'supervisor_1', 'sourceHandle' => 'default', 'targetHandle' => 'tools'],
            ],
        );

        $bindings = $context->toolBindingsFor('supervisor_1');

        $this->assertSame(
            [
                [
                    'ref' => 'node:specialist_1',
                    'exposure' => [
                        'slug' => 'research_agent',
                        'description' => 'Research specialist',
                        'parameters' => [
                            'input' => [
                                'controlled_by' => 'caller',
                                'description' => 'Task for the specialist',
                            ],
                        ],
                    ],
                ],
                ['ref' => 'toolkit:calculator'],
            ],
            $bindings,
        );
    }

    public function test_target_for_handle_skips_toolset_binding_edges(): void
    {
        $context = new GraphContext(
            [
                ['id' => 'specialist_1', 'type' => 'agent', 'data' => ['tool_mode' => true]],
                ['id' => 'supervisor_1', 'type' => 'agent', 'data' => []],
                ['id' => 'stop_1', 'type' => 'stop', 'data' => []],
            ],
            [
                ['source' => 'specialist_1', 'target' => 'supervisor_1', 'sourceHandle' => 'toolset', 'targetHandle' => 'tools'],
                ['source' => 'specialist_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
            ],
        );

        $this->assertSame('stop_1', $context->targetForHandle('specialist_1'));
    }
}
