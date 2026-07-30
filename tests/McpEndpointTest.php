<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\McpServer\McpToolCatalog;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\McpEndpoint;
use DigitalElvis\NeuronAIStudio\Models\McpEndpointBinding;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\ToolDefinition;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use DigitalElvis\NeuronAIStudio\Tools\IssueRefundTool;
use NeuronAI\Tools\Toolkits\Calculator\CalculatorToolkit;
use Orchestra\Testbench\Attributes\DefineEnvironment;

class McpEndpointTest extends TestCase
{
    protected function enableMcpEndpoints($app): void
    {
        $app['config']->set('neuronai-studio.mcp_endpoints.enabled', true);
        $app['config']->set('neuronai-studio.tools.calculator', [
            'label' => 'Calculator',
            'type' => 'toolkit',
            'class' => CalculatorToolkit::class,
        ]);
    }

    public function test_api_key_is_hashed_and_verified(): void
    {
        $endpoint = McpEndpoint::create([
            'name' => 'Demo',
            'slug' => 'demo',
            'enabled' => true,
            'timeout_seconds' => 60,
        ]);

        $plain = $endpoint->rotateApiKey();

        $this->assertTrue($endpoint->fresh()->verifyApiKey($plain));
        $this->assertFalse($endpoint->fresh()->verifyApiKey('wrong'));
        $this->assertNotSame($plain, $endpoint->fresh()->api_key_hash);
    }

    #[DefineEnvironment('enableMcpEndpoints')]
    public function test_catalog_expands_toolkit_with_only_filter(): void
    {
        $endpoint = $this->makeEndpoint();
        $endpoint->bindings()->create([
            'kind' => McpEndpointBinding::KIND_TOOLKIT,
            'ref' => 'toolkit:calculator',
            'only' => ['sum'],
            'enabled' => true,
            'sort_order' => 0,
        ]);

        $tools = app(McpToolCatalog::class)->toolsFor($endpoint->fresh(['bindings']));
        $names = array_column($tools, 'name');

        $this->assertContains('sum', $names);
        $this->assertCount(1, $tools);
    }

    #[DefineEnvironment('enableMcpEndpoints')]
    public function test_catalog_includes_tool_agent_and_workflow(): void
    {
        $tool = ToolDefinition::create([
            'name' => 'Refund',
            'slug' => 'refund',
            'type' => 'builder',
            'description' => 'Issue refunds',
            'input_schema' => [
                ['name' => 'order_id', 'type' => 'string', 'required' => true],
            ],
            'config' => [
                'tool_name' => 'issue_refund',
                'class_path' => IssueRefundTool::class,
            ],
        ]);

        $agent = AgentDefinition::create([
            'name' => 'Support',
            'slug' => 'support',
            'description' => 'Support agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Help users',
        ]);

        $workflow = WorkflowDefinition::create([
            'name' => 'Onboard',
            'slug' => 'onboard',
            'description' => 'Onboarding flow',
            'status' => 'draft',
        ]);

        $endpoint = $this->makeEndpoint();
        $endpoint->bindings()->createMany([
            [
                'kind' => McpEndpointBinding::KIND_TOOL,
                'ref' => $tool->bindingRef(),
                'enabled' => true,
                'sort_order' => 0,
            ],
            [
                'kind' => McpEndpointBinding::KIND_AGENT,
                'ref' => (string) $agent->id,
                'tool_name' => 'ask_support',
                'enabled' => true,
                'sort_order' => 1,
            ],
            [
                'kind' => McpEndpointBinding::KIND_WORKFLOW,
                'ref' => (string) $workflow->id,
                'tool_name' => 'run_onboard',
                'enabled' => true,
                'sort_order' => 2,
            ],
        ]);

        $tools = app(McpToolCatalog::class)->toolsByName($endpoint->fresh(['bindings']));

        $this->assertArrayHasKey('issue_refund', $tools);
        $this->assertArrayHasKey('ask_support', $tools);
        $this->assertArrayHasKey('run_onboard', $tools);
        $this->assertSame('message', array_key_first($tools['ask_support']['inputSchema']['properties']));
    }

    #[DefineEnvironment('enableMcpEndpoints')]
    public function test_http_rejects_missing_api_key(): void
    {
        $endpoint = $this->makeEndpoint(enabled: true);
        $endpoint->rotateApiKey();

        $response = $this->postJson('/api/neuronai/mcp/'.$endpoint->slug, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [],
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
        ]);

        $response->assertUnauthorized();
    }

    #[DefineEnvironment('enableMcpEndpoints')]
    public function test_http_initialize_list_and_call_toolkit_tool(): void
    {
        $endpoint = $this->makeEndpoint(enabled: true);
        $plain = $endpoint->rotateApiKey();

        $endpoint->bindings()->create([
            'kind' => McpEndpointBinding::KIND_TOOLKIT,
            'ref' => 'toolkit:calculator',
            'only' => ['sum'],
            'enabled' => true,
            'sort_order' => 0,
        ]);

        $init = $this->postJson('/api/neuronai/mcp/'.$endpoint->slug, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [],
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
        ], [
            'Authorization' => 'Bearer '.$plain,
        ]);

        $init->assertOk();
        $init->assertJsonPath('result.capabilities.tools.listChanged', false);
        $this->assertNotEmpty($init->headers->get('Mcp-Session-Id'));

        $list = $this->postJson('/api/neuronai/mcp/'.$endpoint->slug, [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
            'params' => [],
        ], [
            'Authorization' => 'Bearer '.$plain,
            'Mcp-Session-Id' => $init->headers->get('Mcp-Session-Id'),
        ]);

        $list->assertOk();
        $names = collect($list->json('result.tools'))->pluck('name')->all();
        $this->assertContains('sum', $names);

        $call = $this->postJson('/api/neuronai/mcp/'.$endpoint->slug, [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'sum',
                'arguments' => ['number1' => 2, 'number2' => 3],
            ],
        ], [
            'Authorization' => 'Bearer '.$plain,
            'x-api-key' => $plain,
        ]);

        $call->assertOk();
        if ($call->json('result.isError')) {
            $this->fail('Tool call error: '.(string) $call->json('result.content.0.text'));
        }
        $this->assertFalse((bool) $call->json('result.isError'));
        $text = $call->json('result.content.0.text');
        $this->assertSame('5', (string) $text);

        $this->assertDatabaseHas(StudioTables::name('runs'), ['status' => 'completed']);
        $this->assertGreaterThan(0, StudioRun::query()->count());
    }

    #[DefineEnvironment('enableMcpEndpoints')]
    public function test_http_disabled_endpoint_is_forbidden(): void
    {
        $endpoint = $this->makeEndpoint(enabled: false);
        $plain = $endpoint->rotateApiKey();

        $response = $this->postJson('/api/neuronai/mcp/'.$endpoint->slug, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'ping',
        ], [
            'Authorization' => 'Bearer '.$plain,
        ]);

        $response->assertForbidden();
    }

    #[DefineEnvironment('enableMcpEndpoints')]
    public function test_http_call_unknown_tool_returns_tool_error(): void
    {
        $endpoint = $this->makeEndpoint(enabled: true);
        $plain = $endpoint->rotateApiKey();

        $response = $this->postJson('/api/neuronai/mcp/'.$endpoint->slug, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'does_not_exist',
                'arguments' => [],
            ],
        ], [
            'x-api-key' => $plain,
        ]);

        $response->assertOk();
        $this->assertTrue((bool) $response->json('result.isError'));
        $this->assertStringContainsString('Unknown tool', (string) $response->json('result.content.0.text'));
    }

    protected function makeEndpoint(bool $enabled = true): McpEndpoint
    {
        return McpEndpoint::create([
            'name' => 'Studio Export',
            'slug' => 'studio-export',
            'description' => 'Test endpoint',
            'enabled' => $enabled,
            'timeout_seconds' => 60,
        ]);
    }
}
