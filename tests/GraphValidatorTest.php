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
}
