<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Registry\ProviderRegistry;
use NeuronAI\Agent\Agent;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\AIProviderInterface;

class DynamicAgent extends Agent
{
    public function __construct(
        protected AIProviderInterface $aiProvider,
        string $instructions = '',
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
}
