<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use NeuronAI\Agent\Agent;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Tools\ProviderToolInterface;
use NeuronAI\Tools\ToolInterface;
use NeuronAI\Tools\Toolkits\ToolkitInterface;

class DynamicAgent extends Agent
{
    /**
     * @param  array<int, ToolInterface|ToolkitInterface|ProviderToolInterface>  $baseTools
     */
    public function __construct(
        protected AIProviderInterface $aiProvider,
        protected ?AgentDefinition $definition = null,
        string $instructions = '',
        protected array $baseTools = [],
        protected ?McpToolResolver $mcpToolResolver = null,
    ) {
        parent::__construct();
        $this->setAiProvider($aiProvider);

        if ($instructions !== '') {
            $this->setInstructions($instructions);
        }
    }

    protected function provider(): AIProviderInterface
    {
        return $this->aiProvider;
    }

    protected function tools(): array
    {
        $tools = $this->baseTools;

        if ($this->definition !== null && $this->mcpToolResolver !== null) {
            $tools = array_merge($tools, $this->mcpToolResolver->toolsForAgent($this->definition));
        }

        return $tools;
    }
}
