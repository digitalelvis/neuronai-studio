<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\ToolApprovalRequiredException;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\AgentNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\LlmNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\StructuredOutput\StructuredOutputResolver;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Tools\Tool;

class WorkflowTokenStreamingTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    protected array $tokens = [];

    protected function agentExecutor(FakeAIProvider $fakeProvider): AgentNodeExecutor
    {
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($fakeProvider);

        $runner = new AgentRunner(
            $registry,
            $this->createMock(ToolResolver::class),
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );

        return new AgentNodeExecutor($runner, new MessageFactory, app(StructuredOutputResolver::class));
    }

    protected function llmExecutor(FakeAIProvider $fakeProvider): LlmNodeExecutor
    {
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($fakeProvider);

        return new LlmNodeExecutor(
            $registry,
            $this->createMock(AgentRunner::class),
            new MessageFactory,
            app(StructuredOutputResolver::class),
        );
    }

    protected function streamingState(GraphContext $context, array $data = []): BuilderWorkflowState
    {
        $this->tokens = [];
        $state = new BuilderWorkflowState($context, null, array_merge(['input' => 'Hi there'], $data));
        $state->stepEmitter = function (string $event, array $eventData): void {
            if ($event === 'token') {
                $this->tokens[] = $eventData;
            }
        };

        return $state;
    }

    /** @return array<int, string> */
    protected function deltas(): array
    {
        return array_column($this->tokens, 'delta');
    }

    public function test_agent_node_streams_tokens_and_aggregates_content(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Streaming Agent',
            'slug' => 'streaming-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        $fakeProvider = new FakeAIProvider(new AssistantMessage('Hello streaming world'));
        $fakeProvider->setStreamChunkSize(4);
        $executor = $this->agentExecutor($fakeProvider);
        $context = new GraphContext([], []);
        $state = $this->streamingState($context);

        $executor->execute([
            'id' => 'agent_1',
            'data' => [
                'agent_id' => $agent->id,
                'output_key' => 'agent_response',
                'stream' => true,
            ],
        ], $state, $context);

        $this->assertGreaterThan(1, count($this->tokens));
        $this->assertSame('agent_1', $this->tokens[0]['node_id']);
        $this->assertSame('Hello streaming world', implode('', $this->deltas()));
        $this->assertSame('Hello streaming world', $state->get('agent_response'));
        $fakeProvider->assertMethodCallCount('stream', 1);
        $fakeProvider->assertMethodCallCount('chat', 0);
    }

    public function test_agent_node_without_stream_flag_uses_blocking_chat(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Blocking Agent',
            'slug' => 'blocking-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        $fakeProvider = new FakeAIProvider(new AssistantMessage('blocking reply'));
        $executor = $this->agentExecutor($fakeProvider);
        $context = new GraphContext([], []);
        $state = $this->streamingState($context);

        $executor->execute([
            'id' => 'agent_1',
            'data' => [
                'agent_id' => $agent->id,
                'output_key' => 'agent_response',
            ],
        ], $state, $context);

        $this->assertSame([], $this->tokens);
        $this->assertSame('blocking reply', $state->get('agent_response'));
        $fakeProvider->assertMethodCallCount('chat', 1);
        $fakeProvider->assertMethodCallCount('stream', 0);
    }

    public function test_agent_node_with_tool_approval_falls_back_to_blocking(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Approval Streaming Agent',
            'slug' => 'approval-streaming-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You act on tools.',
            'require_tool_approval' => true,
        ]);

        $tool = Tool::make('delete_file', 'Deletes a file')
            ->setInputs(['path' => '/tmp/report.txt'])
            ->setCallId('call_1');
        $fakeProvider = new FakeAIProvider(new ToolCallMessage(null, [$tool]));
        $executor = $this->agentExecutor($fakeProvider);
        $context = new GraphContext([], []);
        $state = $this->streamingState($context);

        try {
            $executor->execute([
                'id' => 'agent_1',
                'data' => [
                    'agent_id' => $agent->id,
                    'output_key' => 'agent_response',
                    'stream' => true,
                ],
            ], $state, $context);

            $this->fail('Expected ToolApprovalRequiredException was not thrown.');
        } catch (ToolApprovalRequiredException $exception) {
            $this->assertSame('agent_1', $exception->nodeId);
            $this->assertSame([], $this->tokens);
            $fakeProvider->assertMethodCallCount('stream', 0);
        }
    }

    public function test_llm_node_streams_tokens_and_aggregates_content(): void
    {
        $fakeProvider = new FakeAIProvider(new AssistantMessage('llm streamed text'));
        $fakeProvider->setStreamChunkSize(3);
        $executor = $this->llmExecutor($fakeProvider);
        $context = new GraphContext([], []);
        $state = $this->streamingState($context);

        $executor->execute([
            'id' => 'llm_1',
            'data' => [
                'prompt' => 'Say something',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'output_key' => 'llm_response',
                'stream' => true,
            ],
        ], $state, $context);

        $this->assertGreaterThan(1, count($this->tokens));
        $this->assertSame('llm_1', $this->tokens[0]['node_id']);
        $this->assertSame('llm streamed text', implode('', $this->deltas()));
        $this->assertSame('llm streamed text', $state->get('llm_response'));
        $fakeProvider->assertMethodCallCount('stream', 1);
        $fakeProvider->assertMethodCallCount('chat', 0);
    }

    public function test_llm_node_without_stream_flag_uses_blocking_chat(): void
    {
        $fakeProvider = new FakeAIProvider(new AssistantMessage('plain llm reply'));
        $executor = $this->llmExecutor($fakeProvider);
        $context = new GraphContext([], []);
        $state = $this->streamingState($context);

        $executor->execute([
            'id' => 'llm_1',
            'data' => [
                'prompt' => 'Say something',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'output_key' => 'llm_response',
            ],
        ], $state, $context);

        $this->assertSame([], $this->tokens);
        $this->assertSame('plain llm reply', $state->get('llm_response'));
        $fakeProvider->assertMethodCallCount('chat', 1);
        $fakeProvider->assertMethodCallCount('stream', 0);
    }
}
