<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Registry\McpRegistry;
use NeuronAI\MCP\McpConnector;
use NeuronAI\Tools\ToolInterface;

class McpToolResolver
{
    public function __construct(
        protected McpRegistry $registry,
    ) {}

    /** @return array<int, ToolInterface> */
    public function toolsForAgent(AgentDefinition $agent): array
    {
        $tools = [];

        foreach ($agent->mcpBindings as $binding) {
            foreach ($this->toolsForBinding($binding->mcp_server_slug, [
                'only' => $this->parseOnlyTools($binding->only_tools),
                'exclude' => $binding->exclude_tools ?? [],
            ]) as $tool) {
                $tools[] = $tool;
            }
        }

        return $tools;
    }

    /**
     * @param  array{only?: array<int, string>, exclude?: array<int, string>}  $options
     * @return array<int, ToolInterface>
     */
    public function toolsForBinding(string $slug, array $options = []): array
    {
        $config = $this->registry->resolveConfig($slug);
        $connector = McpConnector::make($config);

        if (! empty($options['exclude'])) {
            $connector->exclude($options['exclude']);
        }

        if (! empty($options['only'])) {
            $connector->only($options['only']);
        }

        return $connector->tools();
    }

    /** @return array<int, string> */
    protected function parseOnlyTools(?string $onlyTools): array
    {
        if ($onlyTools === null || trim($onlyTools) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $onlyTools))));
    }
}
