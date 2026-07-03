<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\AgentNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\NodeExecutorRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\StructuredOutput\StructuredOutputResolver;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Tools\Tool;

class WorkflowToolApprovalTest extends TestCase
{
    public function test_workflow_pauses_awaiting_tool_approval(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Approval Agent',
            'slug' => 'approval-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You act on tools.',
            'require_tool_approval' => true,
        ]);

        $this->bindAgentRunner($this->toolCall());

        $events = [];
        $trace = app(WorkflowRunner::class)->run(
            $this->agentWorkflow($agent->id),
            ['message' => 'Delete the report'],
            function (string $event, array $data) use (&$events) {
                $events[$event] = $data;
            },
        );

        $this->assertSame('awaiting_tool_approval', $trace->status);
        $this->assertSame('agent_1', $trace->awaiting_node_id);
        $this->assertSame('agent_1', $trace->checkpoint['node_id'] ?? null);
        $this->assertSame('delete_file', $trace->checkpoint['pending_tools'][0]['name'] ?? null);
        $this->assertArrayHasKey('tool_approval_required', $events);
        $this->assertSame('delete_file', $events['tool_approval_required']['pending_tools'][0]['name'] ?? null);
    }

    public function test_workflow_completes_when_approval_disabled(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Plain Agent',
            'slug' => 'plain-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
            'require_tool_approval' => false,
        ]);

        $this->bindAgentRunner(new AssistantMessage('All done'));

        $trace = app(WorkflowRunner::class)->run(
            $this->agentWorkflow($agent->id),
            ['message' => 'Say hi'],
        );

        $this->assertSame('completed', $trace->status);
        $this->assertSame('All done', $trace->output['agent_response'] ?? null);
    }

    public function test_workflow_resumes_and_completes_when_tool_approved(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Approval Agent',
            'slug' => 'approval-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You act on tools.',
            'require_tool_approval' => true,
        ]);

        $this->bindAgentRunner($this->toolCall(), new AssistantMessage('Report deleted successfully.'));

        $runner = app(WorkflowRunner::class);
        $paused = $runner->run($this->agentWorkflow($agent->id), ['message' => 'Delete the report']);

        $this->assertSame('awaiting_tool_approval', $paused->status);

        $events = [];
        $completed = $runner->resume(
            $paused,
            'agent_1',
            '',
            function (string $event, array $data) use (&$events) {
                $events[$event] = $data;
            },
            [],
            'approve',
        );

        $this->assertSame('completed', $completed->status);
        $this->assertSame('Report deleted successfully.', $completed->output['agent_response'] ?? null);
        $this->assertArrayHasKey('tool_approval_resolved', $events);
        $this->assertTrue($events['tool_approval_resolved']['approved'] ?? null);
    }

    public function test_workflow_routes_to_rejected_handle_when_tool_rejected(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Rejection Agent',
            'slug' => 'rejection-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You act on tools.',
            'require_tool_approval' => true,
        ]);

        $this->bindAgentRunner($this->toolCall(), new AssistantMessage('Understood, I will not delete anything.'));

        $runner = app(WorkflowRunner::class);
        $paused = $runner->run($this->rejectableAgentWorkflow($agent->id), ['message' => 'Delete the report']);

        $this->assertSame('awaiting_tool_approval', $paused->status);

        $events = [];
        $completed = $runner->resume(
            $paused,
            'agent_1',
            'Do not delete production data.',
            function (string $event, array $data) use (&$events) {
                $events[$event] = $data;
            },
            [],
            'reject',
        );

        $this->assertSame('completed', $completed->status);
        $this->assertSame('rejected', $completed->output['result'] ?? null);
        $this->assertArrayHasKey('tool_approval_resolved', $events);
        $this->assertFalse($events['tool_approval_resolved']['approved'] ?? null);
    }

    protected function toolCall(): ToolCallMessage
    {
        $tool = Tool::make('delete_file', 'Deletes a file')
            ->setCallable(new ApprovableToolHandler)
            ->setInputs(['path' => '/tmp/report.txt'])
            ->setCallId('call_1');

        return new ToolCallMessage(null, [$tool]);
    }

    protected function bindAgentRunner(Message ...$responses): void
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
            new AgentNodeExecutor($runner, new MessageFactory, app(StructuredOutputResolver::class)),
        );
    }

    protected function agentWorkflow(int $agentId): WorkflowDefinition
    {
        return WorkflowDefinition::create([
            'name' => 'Approval Workflow',
            'slug' => 'approval-workflow',
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    ['id' => 'agent_1', 'type' => 'agent', 'position' => ['x' => 200, 'y' => 0], 'data' => [
                        'agent_id' => $agentId,
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
    }

    protected function rejectableAgentWorkflow(int $agentId): WorkflowDefinition
    {
        return WorkflowDefinition::create([
            'name' => 'Rejectable Workflow',
            'slug' => 'rejectable-workflow',
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    ['id' => 'agent_1', 'type' => 'agent', 'position' => ['x' => 200, 'y' => 0], 'data' => [
                        'agent_id' => $agentId,
                        'output_key' => 'agent_response',
                    ]],
                    ['id' => 'set_rejected', 'type' => 'set_state', 'position' => ['x' => 400, 'y' => 100], 'data' => [
                        'key' => 'result',
                        'value' => 'rejected',
                    ]],
                    ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 600, 'y' => 0], 'data' => []],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start_1', 'target' => 'agent_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e2', 'source' => 'agent_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e3', 'source' => 'agent_1', 'target' => 'set_rejected', 'sourceHandle' => 'rejected', 'targetHandle' => 'default'],
                    ['id' => 'e4', 'source' => 'set_rejected', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ],
            ],
        ]);
    }
}

class ApprovableToolHandler
{
    public function __invoke(): string
    {
        return 'file deleted';
    }
}
