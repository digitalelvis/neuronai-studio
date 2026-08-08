<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Http\Middleware\EnsureNeuronAIStudioAuthorized;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioChatMessage;
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
use DigitalElvis\NeuronAIStudio\Support\ChatThreadKey;
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

        $this->assertEquals('completed', $trace->status);
        $this->assertSame($threadId, $trace->output['__studio_thread_id'] ?? null);
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

        $this->assertSame(4, StudioChatMessage::query()->where('thread_id', $threadId)->count());
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

    public function test_thread_endpoint_returns_persisted_messages(): void
    {
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $workflow = WorkflowDefinition::create([
            'name' => 'Thread History Flow',
            'slug' => 'thread-history-flow',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $threadId = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

        StudioChatMessage::create([
            'thread_id' => $threadId,
            'role' => 'user',
            'content' => [['type' => 'text', 'content' => 'Hi']],
        ]);

        StudioChatMessage::create([
            'thread_id' => $threadId,
            'role' => 'assistant',
            'content' => [['type' => 'text', 'content' => 'Hello back']],
        ]);

        $scopedKey = ChatThreadKey::forWorkflow($workflow->id, $threadId);

        StudioChatMessage::create([
            'thread_id' => $scopedKey,
            'role' => 'user',
            'content' => [['type' => 'text', 'content' => 'Scoped hi']],
        ]);

        StudioChatMessage::create([
            'thread_id' => $scopedKey,
            'role' => 'assistant',
            'content' => [['type' => 'text', 'content' => 'Scoped hello']],
        ]);

        $response = $this->getJson(route('neuronai-studio.workflows.chat.threads.show', [
            'workflow' => $workflow->id,
            'thread' => $threadId,
        ]));

        $response->assertOk();
        $response->assertJsonPath('thread_id', $threadId);
        $response->assertJsonCount(4, 'messages');
        $response->assertJsonPath('messages.0.role', 'user');
        $response->assertJsonPath('messages.0.content', 'Hi');
        $response->assertJsonPath('messages.1.content', 'Hello back');
        $response->assertJsonPath('messages.2.content', 'Scoped hi');
        $response->assertJsonPath('messages.3.content', 'Scoped hello');
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
            new AgentNodeExecutor($runner, new MessageFactory, app(StructuredOutputResolver::class)),
        );
    }
}
