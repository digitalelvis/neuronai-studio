<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Tools;

/**
 * Opt-in contract for tools that want a filtered runtime state snapshot
 * (workflow state or agent integrate context) without LLM tool properties.
 */
interface ToolContextAware
{
    public function setToolContext(ToolContext $context): void;
}
