<?php

namespace DigitalElvis\NeuronAIStudio;

use DigitalElvis\NeuronAIStudio\Registry\NodeTypeRegistry;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;

class NeuronAIStudioManager
{
    public function __construct(
        protected NodeTypeRegistry $nodeTypes,
        protected ProviderRegistry $providers,
    ) {}

    public function nodeTypes(): NodeTypeRegistry
    {
        return $this->nodeTypes;
    }

    public function providers(): ProviderRegistry
    {
        return $this->providers;
    }

    public function registerNode(string $type, string $nodeClass, array $meta = []): void
    {
        $this->nodeTypes->register($type, $nodeClass, $meta);
    }
}
