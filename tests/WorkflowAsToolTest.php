<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\Tools\ToolContext;
use DigitalElvis\NeuronAIStudio\Runtime\Tools\WorkflowAsTool;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Support\Str;
use NeuronAI\Tools\ToolInterface;

class WorkflowAsToolTest extends TestCase
{
    protected function childGraphWithGreeting(): array
    {
        return [
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'set_1', 'type' => 'set_state', 'position' => ['x' => 200, 'y' => 0], 'data' => ['key' => 'greeting', 'value' => 'Hello child']],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 400, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'set_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e2', 'source' => 'set_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
            ],
        ];
    }

    public function test_resolver_builds_workflow_as_tool_from_binding(): void
    {
        $resolved = app(ToolResolver::class)->resolve('node:run_wf_1', [
            'exposure' => [
                'slug' => 'run_pricing_flow',
                'description' => 'Run the pricing workflow.',
            ],
            'node' => [
                'id' => 'run_wf_1',
                'type' => 'run_workflow',
                'data' => [
                    'workflow_id' => '1',
                    'message' => '{{input}}',
                    'tool_mode' => true,
                ],
            ],
            'parent_state' => ['input' => 'from-parent'],
            'nesting_depth' => 0,
        ]);

        $this->assertCount(1, $resolved);
        $this->assertInstanceOf(WorkflowAsTool::class, $resolved[0]);
        $this->assertInstanceOf(ToolInterface::class, $resolved[0]);
        $this->assertSame('run_pricing_flow', $resolved[0]->getName());
    }

    public function test_graph_context_resolves_run_workflow_toolset_binding(): void
    {
        $context = new GraphContext(
            [
                [
                    'id' => 'supervisor_1',
                    'type' => 'agent',
                    'data' => ['config_mode' => 'inline'],
                ],
                [
                    'id' => 'run_tool_1',
                    'type' => 'run_workflow',
                    'data' => [
                        'tool_mode' => true,
                        'workflow_id' => '9',
                        'tool_exposure' => [
                            'slug' => 'run_pricing_flow',
                            'description' => 'Pricing',
                        ],
                    ],
                ],
            ],
            [
                ['source' => 'run_tool_1', 'target' => 'supervisor_1', 'sourceHandle' => 'toolset', 'targetHandle' => 'tools'],
            ],
        );

        $bindings = $context->toolBindingsFor('supervisor_1');

        $this->assertCount(1, $bindings);
        $this->assertSame('node:run_tool_1', $bindings[0]['ref']);
        $this->assertSame('run_workflow', $bindings[0]['node']['type']);
        $this->assertSame('run_pricing_flow', $bindings[0]['exposure']['slug']);
    }

    public function test_invoke_runs_child_and_nests_parent_run_id(): void
    {
        $child = WorkflowDefinition::create([
            'name' => 'Tool Child',
            'slug' => 'tool-child-wf',
            'graph' => $this->childGraphWithGreeting(),
        ]);

        $parent = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => StudioThread::create(['id' => (string) Str::uuid()])->id,
            'status' => 'running',
        ]);

        $tool = new WorkflowAsTool(
            'run_pricing_flow',
            'Run pricing',
            [
                'workflow_id' => (string) $child->id,
                'message' => 'default-msg',
                'state_map' => [
                    ['key' => 'lead_id', 'value' => '{{lead_id}}'],
                ],
            ],
            $parent->id,
            parentState: ['lead_id' => '99'],
            nestingDepth: 0,
        );

        $result = $tool('Caller task');

        $this->assertIsString($result);
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertSame('Hello child', $decoded['greeting'] ?? null);
        $this->assertSame('Caller task', $decoded['input'] ?? null);
        $this->assertSame('99', $decoded['lead_id'] ?? null);

        $nested = StudioRun::query()->where('parent_run_id', $parent->id)->first();
        $this->assertNotNull($nested);
        $this->assertSame('completed', $nested->status);
        $this->assertSame(1, $nested->output[WorkflowRunner::NESTING_DEPTH_INPUT_KEY] ?? null);
    }

    public function test_tool_context_merges_into_child_workflow_state(): void
    {
        $child = WorkflowDefinition::create([
            'name' => 'Tool Context Child',
            'slug' => 'tool-context-child-wf',
            'graph' => $this->childGraphWithGreeting(),
        ]);

        $tool = new WorkflowAsTool(
            'run_with_context',
            'Run with context',
            [
                'workflow_id' => (string) $child->id,
                'message' => 'go',
                'state_map' => [
                    ['key' => 'from_map', 'value' => 'mapped'],
                ],
            ],
            parentState: [],
            nestingDepth: 0,
        );

        $tool->setToolContext(ToolContext::fromArray([
            'integration_context' => ['account_id' => 68],
            'include_history' => true,
        ]));

        $decoded = json_decode($tool('hi'), true);

        $this->assertSame(68, $decoded['integration_context']['account_id'] ?? null);
        $this->assertTrue($decoded['include_history'] ?? false);
        $this->assertSame('mapped', $decoded['from_map'] ?? null);
    }

    public function test_invoke_uses_default_message_when_caller_input_empty(): void
    {
        $child = WorkflowDefinition::create([
            'name' => 'Default Msg Child',
            'slug' => 'default-msg-child',
            'graph' => $this->childGraphWithGreeting(),
        ]);

        $tool = new WorkflowAsTool(
            'run_flow',
            'Run',
            [
                'workflow_id' => (string) $child->id,
                'message' => '{{topic}}',
            ],
            parentState: ['topic' => 'from-template'],
        );

        $result = $tool('');

        $decoded = json_decode($result, true);
        $this->assertSame('from-template', $decoded['input'] ?? null);
    }

    public function test_invoke_returns_error_string_on_depth_exceeded(): void
    {
        $child = WorkflowDefinition::create([
            'name' => 'Deep Tool Child',
            'slug' => 'deep-tool-child',
            'graph' => $this->childGraphWithGreeting(),
        ]);

        $tool = new WorkflowAsTool(
            'run_flow',
            'Run',
            ['workflow_id' => (string) $child->id, 'message' => 'x'],
            nestingDepth: 3,
        );

        $result = $tool('hi');

        $this->assertStringStartsWith('Error: ', $result);
        $this->assertStringContainsString('nesting depth', $result);
    }

    public function test_invoke_returns_error_string_on_hitl(): void
    {
        $child = WorkflowDefinition::create([
            'name' => 'Hitl Tool Child',
            'slug' => 'hitl-tool-child',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $paused = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => StudioThread::create(['id' => (string) Str::uuid()])->id,
            'status' => 'awaiting_input',
        ]);

        $runner = $this->createMock(WorkflowRunner::class);
        $runner->method('run')->willReturn($paused);
        $this->app->instance(WorkflowRunner::class, $runner);

        $tool = new WorkflowAsTool(
            'run_flow',
            'Run',
            ['workflow_id' => (string) $child->id, 'message' => 'x'],
        );

        $result = $tool('hi');

        $this->assertStringStartsWith('Error: ', $result);
        $this->assertStringContainsString('human interrupt', $result);
    }
}
