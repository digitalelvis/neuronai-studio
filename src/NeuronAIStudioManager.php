<?php

namespace ElvisLopesDigital\NeuronAIStudio;

use ElvisLopesDigital\NeuronAIStudio\Registry\NodeTypeRegistry;
use ElvisLopesDigital\NeuronAIStudio\Registry\ProviderRegistry;

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
