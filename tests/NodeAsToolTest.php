<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\Tools\NodeAsTool;
use Illuminate\Support\Str;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Tools\ToolInterface;

class NodeAsToolTest extends TestCase
{
    public function test_resolver_builds_node_as_tool_from_binding(): void
    {
        $resolved = app(ToolResolver::class)->resolve('node:specialist_1', [
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
            'node' => [
                'id' => 'specialist_1',
                'type' => 'agent',
                'data' => [
                    'config_mode' => 'inline',
                    'provider' => 'openai',
                    'model' => 'gpt-4o-mini',
                    'instructions' => 'You research topics.',
                    'tool_mode' => true,
                ],
            ],
        ]);

        $this->assertCount(1, $resolved);
        $this->assertInstanceOf(NodeAsTool::class, $resolved[0]);
        $this->assertInstanceOf(ToolInterface::class, $resolved[0]);
        $this->assertSame('research_agent', $resolved[0]->getName());
    }

    public function test_invoke_returns_specialist_content(): void
    {
        $provider = new FakeAIProvider(new AssistantMessage('Specialist result'));

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($provider);

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

        $parent = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => StudioThread::create(['id' => (string) Str::uuid()])->id,
            'status' => 'running',
        ]);

        $tool = new NodeAsTool(
            'research_agent',
            'Research specialist',
            [
                'config_mode' => 'inline',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'instructions' => 'You research topics.',
            ],
            $parent->id,
        );

        $result = $tool('Find papers on RAG');

        $this->assertSame('Specialist result', $result);

        $child = StudioRun::query()
            ->where('parent_run_id', $parent->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($child);
        $this->assertSame('completed', $child->status);
    }

    public function test_invoke_returns_error_string_on_failure(): void
    {
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willThrowException(new \RuntimeException('provider down'));

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

        $tool = new NodeAsTool(
            'research_agent',
            'Research specialist',
            [
                'config_mode' => 'inline',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
            ],
        );

        $result = $tool('Find papers on RAG');

        $this->assertStringStartsWith('Error: ', $result);
        $this->assertStringContainsString('provider down', $result);
    }
}
