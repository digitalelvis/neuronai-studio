<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime;

use NeuronAI\Agent\Agent;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Tools\ProviderToolInterface;
use NeuronAI\Tools\ToolInterface;
use NeuronAI\Tools\Toolkits\ToolkitInterface;

class DynamicAgent extends Agent
{
    /**
     * @param  array<int, ToolInterface|ToolkitInterface|ProviderToolInterface>  $tools
     */
    public function __construct(
        protected AIProviderInterface $aiProvider,
        string $instructions = '',
        array $tools = [],
    ) {
        parent::__construct();
        $this->setAiProvider($aiProvider);

        if ($instructions !== '') {
            $this->setInstructions($instructions);
        }

        if ($tools !== []) {
            $this->addTool($tools);
        }
    }

    protected function provider(): AIProviderInterface
    {
        return $this->aiProvider;
    }
}
