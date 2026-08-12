<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Integration\WorkflowStreamBridge;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\GraphValidator;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\AgentNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\NodeExecutorRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\StructuredOutput\StructuredOutputResolver;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowReplyResolver;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Stream\Adapters\VercelAIAdapter;
use NeuronAI\Testing\FakeAIProvider;

class WorkflowReplyContractTest extends TestCase
{
    protected function fakeAgentProvider(string $text = 'Agent says hi'): FakeAIProvider
    {
        $provider = new FakeAIProvider(new AssistantMessage($text));
        $provider->setStreamChunkSize(4);

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

        $this->app->instance(ProviderRegistry::class, $registry);
        $this->app->instance(AgentRunner::class, $runner);
        $this->app->make(NodeExecutorRegistry::class)->register(
            'agent',
            new AgentNodeExecutor($runner, new MessageFactory, app(StructuredOutputResolver::class)),
        );

        return $provider;
    }

    public function test_stop_writes_reply_to_state(): void
    {
        $this->fakeAgentProvider('Hello from agent');

        $agent = AgentDefinition::create([
            'name' => 'Reply Agent',
            'slug' => 'reply-agent-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Helpful',
        ]);

        $workflow = WorkflowDefinition::create([
            'name' => 'Reply Stop Flow',
            'slug' => 'reply-stop-'.uniqid(),
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    ['id' => 'agent_1', 'type' => 'agent', 'position' => ['x' => 200, 'y' => 0], 'data' => [
                        'agent_id' => $agent->id,
                        'output_key' => 'agent_response',
                    ]],
                    ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 400, 'y' => 0], 'data' => [
                        'reply' => '{{agent_response}}',
                    ]],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start_1', 'target' => 'agent_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e2', 'source' => 'agent_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ],
            ],
        ]);

        $run = app(WorkflowRunner::class)->run($workflow, ['input' => 'hi']);

        $this->assertSame('completed', $run->status);
        $this->assertSame('Hello from agent', $run->output['reply'] ?? null);
        $this->assertSame(
            'Hello from agent',
            app(WorkflowReplyResolver::class)->textFromRun($run),
        );
    }

    public function test_resolver_prefers_reply_over_legacy_last_string(): void
    {
        $resolver = app(WorkflowReplyResolver::class);

        $text = $resolver->textFromOutput([
            'input' => 'user',
            'intent' => 'duvidas',
            'file_resume' => 'internal transcript',
            'agent_response' => 'Real answer',
            'reply' => 'Canonical reply',
        ]);

        $this->assertSame('Canonical reply', $text);
    }

    public function test_resolver_legacy_skips_empty_agent_response(): void
    {
        $resolver = app(WorkflowReplyResolver::class);

        $text = $resolver->textFromOutput([
            'intent' => 'x',
            'agent_response' => '',
            'file_resume' => 'transcript only',
        ]);

        $this->assertSame('transcript only', $text);
    }

    public function test_duplicate_handle_is_validation_error(): void
    {
        $result = app(GraphValidator::class)->validate([
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'data' => []],
                ['id' => 'loop_1', 'type' => 'loop', 'data' => ['state_key' => 'x', 'operator' => 'not_empty']],
                ['id' => 'a', 'type' => 'set_state', 'data' => ['key' => 'a', 'value' => '1']],
                ['id' => 'b', 'type' => 'set_state', 'data' => ['key' => 'b', 'value' => '2']],
                ['id' => 'stop_1', 'type' => 'stop', 'data' => ['reply' => 'ok']],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'loop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e2', 'source' => 'loop_1', 'target' => 'a', 'sourceHandle' => 'continue', 'targetHandle' => 'default'],
                ['id' => 'e3', 'source' => 'loop_1', 'target' => 'b', 'sourceHandle' => 'continue', 'targetHandle' => 'default'],
                ['id' => 'e4', 'source' => 'loop_1', 'target' => 'stop_1', 'sourceHandle' => 'exit', 'targetHandle' => 'default'],
                ['id' => 'e5', 'source' => 'a', 'target' => 'loop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e6', 'source' => 'b', 'target' => 'loop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
            ],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn (string $e) => str_contains($e, "multiple control-flow edges on handle 'continue'")),
        );
    }

    public function test_reply_contract_warnings_for_stop_and_condition(): void
    {
        $result = app(GraphValidator::class)->validate([
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'data' => []],
                ['id' => 'cond_1', 'type' => 'condition', 'data' => ['state_key' => 'x', 'operator' => 'not_empty']],
                ['id' => 'stop_1', 'type' => 'stop', 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'cond_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e2', 'source' => 'cond_1', 'target' => 'stop_1', 'sourceHandle' => 'true', 'targetHandle' => 'default'],
            ],
        ]);

        $this->assertTrue($result['valid']);
        $warnings = $result['warnings'] ?? [];
        $this->assertTrue(collect($warnings)->contains(fn (string $w) => str_contains($w, 'no reply template')));
        $this->assertTrue(collect($warnings)->contains(fn (string $w) => str_contains($w, "no 'false' edge")));
    }

    public function test_stream_bridge_emits_human_prompt_as_text(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Human Reply Flow',
            'slug' => 'human-reply-'.uniqid(),
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    ['id' => 'human_1', 'type' => 'human', 'position' => ['x' => 200, 'y' => 0], 'data' => [
                        'prompt' => 'Which instance?',
                        'output_key' => 'human_response',
                    ]],
                    ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 400, 'y' => 0], 'data' => [
                        'reply' => 'done',
                    ]],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start_1', 'target' => 'human_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e2', 'source' => 'human_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ],
            ],
        ]);

        $lines = [];
        $bridge = new WorkflowStreamBridge(new VercelAIAdapter);
        $run = $bridge->run(
            sink: function (string $line) use (&$lines): void {
                $lines[] = $line;
            },
            execute: function (callable $emitter) use ($workflow): StudioRun {
                return app(WorkflowRunner::class)->run($workflow, ['input' => 'hi'], emitter: $emitter);
            },
        );

        $this->assertSame('awaiting_input', $run->status);
        $joined = implode('', $lines);
        $this->assertStringContainsString('Which instance?', $joined);
        $this->assertStringContainsString('awaiting_input', $joined);
        $this->assertSame('Which instance?', $run->checkpoint_state['prompt'] ?? null);
    }

    public function test_stream_bridge_skips_tokens_when_publish_reply_false(): void
    {
        $this->fakeAgentProvider('Should not leak');

        $agent = AgentDefinition::create([
            'name' => 'Internal Stream Agent',
            'slug' => 'internal-stream-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Helpful',
        ]);

        $workflow = WorkflowDefinition::create([
            'name' => 'Internal Stream Flow',
            'slug' => 'internal-stream-flow-'.uniqid(),
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    ['id' => 'agent_1', 'type' => 'agent', 'position' => ['x' => 200, 'y' => 0], 'data' => [
                        'agent_id' => $agent->id,
                        'output_key' => 'agent_response',
                        'stream' => true,
                        'publish_reply' => false,
                    ]],
                    ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 400, 'y' => 0], 'data' => [
                        'reply' => 'Final: {{agent_response}}',
                    ]],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start_1', 'target' => 'agent_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e2', 'source' => 'agent_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ],
            ],
        ]);

        $lines = [];
        $bridge = new WorkflowStreamBridge(new VercelAIAdapter);
        $run = $bridge->run(
            sink: function (string $line) use (&$lines): void {
                $lines[] = $line;
            },
            execute: function (callable $emitter) use ($workflow): StudioRun {
                return app(WorkflowRunner::class)->run($workflow, ['input' => 'hi'], emitter: $emitter);
            },
        );

        $this->assertSame('completed', $run->status);
        $this->assertSame('Final: Should not leak', $run->output['reply'] ?? null);
        $joined = implode('', $lines);
        $this->assertStringContainsString('Final: Should not leak', $joined);
    }
}
