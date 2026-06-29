<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime\NodeExecutors;

use ElvisLopesDigital\NeuronAIStudio\Registry\McpRegistry;
use ElvisLopesDigital\NeuronAIStudio\Runtime\GraphContext;
use ElvisLopesDigital\NeuronAIStudio\Runtime\McpToolResolver;
use NeuronAI\MCP\McpConnector;
use NeuronAI\Tools\ToolInterface;
use NeuronAI\Workflow\WorkflowState;

class McpNodeExecutor implements NodeExecutorInterface
{
    public function __construct(
        protected McpRegistry $mcpRegistry,
        protected McpToolResolver $mcpToolResolver,
    ) {}

    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = $nodeConfig['data'] ?? [];
        $outputKey = $data['output_key'] ?? 'mcp_result';
        $serverSlug = $data['mcp_server'] ?? null;
        $toolName = $data['tool_name'] ?? null;

        try {
            if (! is_string($serverSlug) || $serverSlug === '') {
                throw new \RuntimeException('MCP server is not configured.');
            }

            if (! is_string($toolName) || $toolName === '') {
                throw new \RuntimeException('MCP tool name is not configured.');
            }

            $tools = $this->mcpToolResolver->toolsForBinding($serverSlug, [
                'only' => [$toolName],
            ]);

            if ($tools === []) {
                throw new \RuntimeException("MCP tool [{$toolName}] was not found on server [{$serverSlug}].");
            }

            $tool = $tools[0];

            if (! $tool instanceof ToolInterface) {
                throw new \RuntimeException('Resolved MCP reference is not an executable tool.');
            }

            $parameters = $this->resolveParameters($data, $state);
            $tool->setInputs($parameters);
            $tool->execute();

            $state->set($outputKey, [
                'server' => $serverSlug,
                'name' => $tool->getName(),
                'result' => $tool->getResult(),
            ]);
        } catch (\Throwable $exception) {
            $state->set($outputKey, ['error' => $exception->getMessage()]);
        }

        return 'default';
    }

    /** @return array<string, mixed> */
    protected function resolveParameters(array $data, WorkflowState $state): array
    {
        $parameters = $data['parameters'] ?? [];

        if (! is_array($parameters)) {
            return [];
        }

        $resolved = [];

        foreach ($parameters as $key => $value) {
            if (is_string($value) && str_starts_with($value, '$')) {
                $resolved[$key] = $state->get(ltrim($value, '$'));
            } else {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }
}
