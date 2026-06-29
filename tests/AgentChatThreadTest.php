<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Http\Middleware\EnsureNeuronAIStudioAuthorized;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioChatMessage;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Support\ChatThreadKey;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;

class AgentChatThreadTest extends TestCase
{
    public function test_stream_persists_messages_for_thread(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Thread Agent',
            'slug' => 'thread-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        $threadId = '550e8400-e29b-41d4-a716-446655440000';
        $runner = $this->runnerWithFakeProvider(new AssistantMessage('Hello back'));

        foreach ($runner->stream($agent, ['message' => 'Hi', 'thread_id' => $threadId]) as $chunk) {
        }

        $scopedKey = ChatThreadKey::forAgent($agent->id, $threadId);

        $this->assertSame(2, StudioChatMessage::query()->where('thread_id', $scopedKey)->count());
    }

    public function test_thread_endpoint_returns_persisted_messages(): void
    {
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $agent = AgentDefinition::create([
            'name' => 'Thread Agent',
            'slug' => 'thread-agent-endpoint',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        $threadId = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
        $scopedKey = ChatThreadKey::forAgent($agent->id, $threadId);

        StudioChatMessage::create([
            'thread_id' => $scopedKey,
            'role' => 'user',
            'content' => [['type' => 'text', 'content' => 'Hi']],
        ]);

        StudioChatMessage::create([
            'thread_id' => $scopedKey,
            'role' => 'assistant',
            'content' => [['type' => 'text', 'content' => 'Hello back']],
        ]);

        $response = $this->getJson(route('neuronai-studio.agents.chat.threads.show', [
            'agent' => $agent->id,
            'thread' => $threadId,
        ]));

        $response->assertOk();
        $response->assertJsonPath('thread_id', $threadId);
        $response->assertJsonCount(2, 'messages');
        $response->assertJsonPath('messages.0.role', 'user');
        $response->assertJsonPath('messages.0.content', 'Hi');
        $response->assertJsonPath('messages.1.content', 'Hello back');
    }

    public function test_stream_endpoint_emits_thread_event(): void
    {
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $agent = AgentDefinition::create([
            'name' => 'Stream Thread Agent',
            'slug' => 'stream-thread-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        $threadId = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn(new FakeAIProvider(new AssistantMessage('Done.')));

        $toolResolver = $this->createMock(ToolResolver::class);
        $toolResolver->method('resolveMany')->willReturn([]);

        $this->app->instance(AgentRunner::class, new AgentRunner(
            $registry,
            $toolResolver,
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        ));

        $response = $this->post(route('neuronai-studio.agents.chat.stream', $agent), [
            'message' => 'Hello',
            'thread_id' => $threadId,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('event: thread', $response->streamedContent());
        $this->assertStringContainsString($threadId, $response->streamedContent());
    }

    protected function runnerWithFakeProvider(AssistantMessage $response): AgentRunner
    {
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn(new FakeAIProvider($response));

        $toolResolver = $this->createMock(ToolResolver::class);
        $toolResolver->method('resolveMany')->willReturn([]);

        return new AgentRunner(
            $registry,
            $toolResolver,
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );
    }
}
