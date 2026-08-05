<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Tools;

trait InteractsWithToolContext
{
    protected ?ToolContext $toolContext = null;

    public function setToolContext(ToolContext $context): void
    {
        $this->toolContext = $context;
    }

    public function toolContext(): ?ToolContext
    {
        return $this->toolContext;
    }

    public function contextGet(string $key, mixed $default = null): mixed
    {
        return $this->toolContext?->get($key, $default) ?? $default;
    }
}
