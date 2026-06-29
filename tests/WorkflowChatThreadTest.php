<?php

namespace ElvisLopesDigital\NeuronAIStudio\Tests;

use ElvisLopesDigital\NeuronAIStudio\Http\Middleware\EnsureNeuronAIStudioAuthorized;
use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Models\StudioChatMessage;
use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowDefinition;
use ElvisLopesDigital\NeuronAIStudio\Registry\ProviderRegistry;
use ElvisLopesDigital\NeuronAIStudio\Runtime\AgentRunner;
use ElvisLopesDigital\NeuronAIStudio\Runtime\McpToolResolver;
use ElvisLopesDigital\NeuronAIStudio\Runtime\MessageFactory;
use ElvisLopesDigital\NeuronAIStudio\Runtime\NodeExecutors\AgentNodeExecutor;
use ElvisLopesDigital\NeuronAIStudio\Runtime\NodeExecutors\NodeExecutorRegistry;
use ElvisLopesDigital\NeuronAIStudio\Runtime\ToolEventExtractor;
use ElvisLopesDigital\NeuronAIStudio\Runtime\ToolResolver;
use ElvisLopesDigital\NeuronAIStudio\Runtime\WorkflowRunner;
use ElvisLopesDigital\NeuronAIStudio\Support\ChatThreadKey;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;

class WorkflowChatThreadTest extends TestCase
{
    public function test_workflow_run_sets_studio_thread_id_in_state(): void
    {
        $workflow = $this->workflowWithAgentNode();
        $threadId = '550e8400-e29b-41d4-a716-446655440000';

        $this->bindFakeAgentRunner(new AssistantMessage('Hello back'));

        $trace = app(WorkflowRunner::class)->run($workflow, [
            'message' => 'Hi',
            'thread_id' => $threadId,
        ]);

        $expectedKey = ChatThreadKey::forWorkflow($workflow->id, $threadId);

        $this->assertEquals('completed', $trace->status);
        $this->assertSame($expectedKey, $trace->output['__studio_thread_id'] ?? null);
    }

    public function test_agent_node_persists_messages_for_workflow_thread_across_runs(): void
    {
        $workflow = $this->workflowWithAgentNode();
        $threadId = '550e8400-e29b-41d4-a716-446655440000';
        $scopedKey = ChatThreadKey::forWorkflow($workflow->id, $threadId);

        $this->bindFakeAgentRunner(
            new AssistantMessage('Hello back'),
            new AssistantMessage('Follow up response'),
        );

        $runner = app(WorkflowRunner::class);

        $runner->run($workflow, ['message' => 'Hi', 'thread_id' => $threadId]);
        $runner->run($workflow, ['message' => 'Follow up', 'thread_id' => $threadId]);

        $this->assertSame(4, StudioChatMessage::query()->where('thread_id', $scopedKey)->count());
    }

    public function test_workflow_stream_endpoint_emits_thread_event(): void
    {
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $workflow = WorkflowDefinition::create([
            'name' => 'Stream Thread Flow',
            'slug' => 'stream-thread-flow',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $threadId = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';

        $response = $this->post(route('neuronai-studio.workflows.trace.stream', $workflow), [
            'message' => 'Hello',
            'thread_id' => $threadId,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('event: thread', $response->streamedContent());
        $this->assertStringContainsString($threadId, $response->streamedContent());
    }

    protected function workflowWithAgentNode(): WorkflowDefinition
    {
        $agent = AgentDefinition::create([
            'name' => 'Workflow Thread Agent',
            'slug' => 'workflow-thread-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        return WorkflowDefinition::create([
            'name' => 'Agent Thread Flow',
            'slug' => 'agent-thread-flow',
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
    }

    protected function bindFakeAgentRunner(AssistantMessage ...$responses): void
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
    }
}
