<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\AgentMcpServer;
use DigitalElvis\NeuronAIStudio\Registry\McpRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Tests\Support\FakeMcpTransport;
use Mockery;

class McpToolResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_tools_for_agent_applies_only_and_exclude_filters(): void
    {
        $registry = Mockery::mock(McpRegistry::class);
        $registry->shouldReceive('resolveConfig')
            ->once()
            ->with('fake')
            ->andReturn([
                'transport' => new FakeMcpTransport([
                    ['name' => 'allowed', 'description' => 'Allowed', 'inputSchema' => ['properties' => []]],
                    ['name' => 'blocked', 'description' => 'Blocked', 'inputSchema' => ['properties' => []]],
                ]),
            ]);

        $agent = AgentDefinition::create([
            'name' => 'MCP Agent',
            'slug' => 'mcp-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
        ]);

        AgentMcpServer::create([
            'agent_definition_id' => $agent->id,
            'mcp_server_slug' => 'fake',
            'only_tools' => 'allowed',
            'exclude_tools' => null,
        ]);

        $agent->load('mcpBindings');

        $tools = (new McpToolResolver($registry))->toolsForAgent($agent);

        $this->assertCount(1, $tools);
        $this->assertSame('allowed', $tools[0]->getName());
    }
}
