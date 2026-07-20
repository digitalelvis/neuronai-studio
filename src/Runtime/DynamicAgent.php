<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioChatMessage;
use DigitalElvis\NeuronAIStudio\Runtime\Memory\MemoryConfig;
use NeuronAI\Agent\Agent;
use NeuronAI\Chat\History\ChatHistoryInterface;
use NeuronAI\Chat\History\EloquentChatHistory;
use NeuronAI\Chat\History\InMemoryChatHistory;
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
        protected ?string $threadId = null,
        protected ?int $contextWindow = null,
        protected MemoryConfig $memoryConfig = new MemoryConfig,
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

    protected function chatHistory(): ChatHistoryInterface
    {
        $contextWindow = $this->contextWindow ?? (int) config('neuronai-studio.chat_history_context_window', 150000);
        $forceInMemory = $this->memoryConfig->driver() === MemoryConfig::DRIVER_IN_MEMORY;

        if ($forceInMemory || $this->threadId === null) {
            return new InMemoryChatHistory(contextWindow: $contextWindow);
        }

        return new EloquentChatHistory(
            threadId: $this->threadId,
            modelClass: StudioChatMessage::class,
            contextWindow: $contextWindow,
        );
    }
}
