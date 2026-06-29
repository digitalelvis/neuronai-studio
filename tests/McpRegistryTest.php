<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\McpServer;
use DigitalElvis\NeuronAIStudio\Registry\McpRegistry;
use DigitalElvis\NeuronAIStudio\Tests\Support\FakeMcpTransport;

class McpRegistryTest extends TestCase
{
    public function test_all_merges_config_and_database_with_db_override(): void
    {
        config([
            'neuronai-studio.mcp_servers' => [
                'shared' => [
                    'label' => 'Config Shared',
                    'transport' => 'http',
                    'url' => 'https://config.example/mcp',
                ],
                'config-only' => [
                    'label' => 'Config Only',
                    'transport' => 'http',
                    'url' => 'https://config-only.example/mcp',
                ],
            ],
        ]);

        McpServer::create([
            'name' => 'Database Shared',
            'slug' => 'shared',
            'transport' => 'http',
            'url' => 'https://db.example/mcp',
            'enabled' => true,
        ]);

        $registry = app(McpRegistry::class);
        $all = $registry->all();

        $this->assertSame('Database Shared', $all['shared']['label']);
        $this->assertSame('config', $all['config-only']['source']);
        $this->assertSame('database', $all['shared']['source']);
    }

    public function test_resolve_token_reads_environment_variable(): void
    {
        putenv('TEST_MCP_TOKEN=secret-token');

        $token = app(McpRegistry::class)->resolveToken('TEST_MCP_TOKEN');

        $this->assertSame('secret-token', $token);

        putenv('TEST_MCP_TOKEN');
    }

    public function test_test_connection_from_payload_uses_fake_transport(): void
    {
        $registry = app(McpRegistry::class);

        $result = $registry->testConnectionFromPayload([
            'slug' => 'preview',
            'transport' => 'stdio',
            'command' => 'npx',
            'connector' => [
                'transport' => new FakeMcpTransport([
                    ['name' => 'search', 'description' => 'Search', 'inputSchema' => ['properties' => []]],
                    ['name' => 'write', 'description' => 'Write', 'inputSchema' => ['properties' => []]],
                ]),
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(['search', 'write'], $result['tools']);
    }

    public function test_stdio_allowlist_blocks_unlisted_commands(): void
    {
        config(['neuronai-studio.mcp_stdio_allowlist' => ['npx']]);

        $this->expectException(\InvalidArgumentException::class);

        app(McpRegistry::class)->assertStdioCommandAllowed('bash');
    }
}
