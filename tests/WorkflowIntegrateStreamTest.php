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
use Illuminate\Routing\Middleware\SubstituteBindings;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;
use Orchestra\Testbench\Attributes\DefineEnvironment;

class WorkflowIntegrateStreamTest extends TestCase
{
    /**
     * Bind a fake provider and re-register the `agent` node executor with it.
     * Workflow node executors are constructed at boot (before the container swap
     * would take effect), so we replace the executor in the registry directly.
     */
    protected function fakeProvider(string $text = 'Hello workflow stream'): FakeAIProvider
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

    protected function agentWorkflow(): WorkflowDefinition
    {
        $agent = AgentDefinition::create([
            'name' => 'Workflow Stream Agent',
            'slug' => 'workflow-stream-agent-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        return WorkflowDefinition::create([
            'name' => 'Agent Stream Flow',
            'slug' => 'agent-stream-flow-'.uniqid(),
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    ['id' => 'agent_1', 'type' => 'agent', 'position' => ['x' => 200, 'y' => 0], 'data' => [
                        'agent_id' => $agent->id,
                        'output_key' => 'agent_response',
                        'stream' => true,
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

    protected function humanWorkflow(): WorkflowDefinition
    {
        return WorkflowDefinition::create([
            'name' => 'Integrate Human Flow',
            'slug' => 'integrate-human-flow-'.uniqid(),
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    ['id' => 'human_1', 'type' => 'human', 'position' => ['x' => 200, 'y' => 0], 'data' => [
                        'prompt' => 'Confirm order id',
                        'output_key' => 'order_id',
                    ]],
                    ['id' => 'set_1', 'type' => 'set_state', 'position' => ['x' => 400, 'y' => 0], 'data' => [
                        'key' => 'confirmed',
                        'from_key' => 'order_id',
                    ]],
                    ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 600, 'y' => 0], 'data' => []],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start_1', 'target' => 'human_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e2', 'source' => 'human_1', 'target' => 'set_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e3', 'source' => 'set_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ],
            ],
        ]);
    }

    #[DefineEnvironment('lightIntegrationMiddleware')]
    public function test_vercel_protocol_streams_workflow_text_delta(): void
    {
        $this->fakeProvider('Hello workflow stream');
        $workflow = $this->agentWorkflow();

        $response = $this->postJson(
            route('neuronai-studio.integrate.workflows.stream', ['workflow' => $workflow, 'protocol' => 'vercel']),
            ['message' => 'run it'],
        );

        $response->assertOk();
        $response->assertHeader('x-vercel-ai-ui-message-stream', 'v1');

        $content = $response->streamedContent();
        $this->assertStringContainsString('"type":"text-delta"', $content);
        $this->assertStringContainsString('"type":"start"', $content);
        $this->assertStringContainsString('[DONE]', $content);
    }

    #[DefineEnvironment('lightIntegrationMiddleware')]
    public function test_agui_protocol_emits_run_started_and_finished(): void
    {
        $this->fakeProvider('Hello agui workflow');
        $workflow = $this->agentWorkflow();

        $response = $this->postJson(
            route('neuronai-studio.integrate.workflows.stream', ['workflow' => $workflow, 'protocol' => 'agui']),
            ['message' => 'run it'],
        );

        $response->assertOk();

        $content = $response->streamedContent();
        $this->assertStringContainsString('RUN_STARTED', $content);
        $this->assertStringContainsString('TEXT_MESSAGE_CONTENT', $content);
        $this->assertStringContainsString('RUN_FINISHED', $content);
    }

    #[DefineEnvironment('lightIntegrationMiddleware')]
    public function test_human_node_pause_signals_trace_id_vercel(): void
    {
        $workflow = $this->humanWorkflow();

        $response = $this->postJson(
            route('neuronai-studio.integrate.workflows.stream', ['workflow' => $workflow, 'protocol' => 'vercel']),
            ['message' => 'start'],
        );

        $response->assertOk();

        $content = $response->streamedContent();
        $this->assertStringContainsString('awaiting_input', $content);
        $this->assertStringContainsString('"trace_id"', $content);
        $this->assertStringContainsString('[DONE]', $content);
    }

    #[DefineEnvironment('lightIntegrationMiddleware')]
    public function test_human_node_pause_signals_trace_id_agui(): void
    {
        $workflow = $this->humanWorkflow();

        $response = $this->postJson(
            route('neuronai-studio.integrate.workflows.stream', ['workflow' => $workflow, 'protocol' => 'agui']),
            ['message' => 'start'],
        );

        $response->assertOk();

        $content = $response->streamedContent();
        $this->assertStringContainsString('awaiting_input', $content);
        $this->assertStringContainsString('"trace_id"', $content);
        $this->assertStringContainsString('RUN_FINISHED', $content);
    }

    #[DefineEnvironment('lightIntegrationMiddleware')]
    public function test_unknown_protocol_returns_404(): void
    {
        $workflow = $this->humanWorkflow();

        $response = $this->postJson(
            route('neuronai-studio.integrate.workflows.stream', ['workflow' => $workflow, 'protocol' => 'bogus']),
            ['message' => 'start'],
        );

        $response->assertNotFound();
    }

    protected function lightIntegrationMiddleware($app): void
    {
        $app['config']->set('neuronai-studio.stream_adapters.middleware', [SubstituteBindings::class]);
    }
}
