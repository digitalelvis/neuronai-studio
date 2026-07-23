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
}
