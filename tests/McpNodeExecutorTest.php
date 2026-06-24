<?php

namespace ElvisLopesDigital\NeuronAIStudio\Tests;

use ElvisLopesDigital\NeuronAIStudio\Registry\McpRegistry;
use ElvisLopesDigital\NeuronAIStudio\Runtime\BuilderWorkflowState;
use ElvisLopesDigital\NeuronAIStudio\Runtime\GraphContext;
use ElvisLopesDigital\NeuronAIStudio\Runtime\McpToolResolver;
use ElvisLopesDigital\NeuronAIStudio\Runtime\NodeExecutors\McpNodeExecutor;
use ElvisLopesDigital\NeuronAIStudio\Tests\Support\FakeMcpTransport;
use Mockery;

class McpNodeExecutorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_execute_runs_selected_mcp_tool(): void
    {
        $registry = Mockery::mock(McpRegistry::class);

        $resolver = new McpToolResolver($registry);
        $registry->shouldReceive('resolveConfig')
            ->once()
            ->with('fake')
            ->andReturn([
                'transport' => new FakeMcpTransport([
                    [
                        'name' => 'echo',
                        'description' => 'Echo text',
                        'inputSchema' => [
                            'properties' => [
                                'message' => ['type' => 'string'],
                            ],
                            'required' => ['message'],
                        ],
                    ],
                ]),
            ]);

        $executor = new McpNodeExecutor($registry, $resolver);
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, ['input' => 'hello']);

        $executor->execute([
            'data' => [
                'mcp_server' => 'fake',
                'tool_name' => 'echo',
                'output_key' => 'mcp_result',
                'parameters' => [
                    'message' => '$input',
                ],
            ],
        ], $state, $context);

        $result = $state->get('mcp_result');

        $this->assertSame('fake', $result['server']);
        $this->assertSame('echo', $result['name']);
        $this->assertNotEmpty($result['result']);
    }
}
