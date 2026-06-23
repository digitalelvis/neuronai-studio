<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime\NodeExecutors;

use ElvisLopesDigital\NeuronAIStudio\Runtime\GraphContext;
use InvalidArgumentException;
use NeuronAI\Workflow\WorkflowState;

class NodeExecutorRegistry
{
    /** @var array<string, NodeExecutorInterface> */
    protected array $executors = [];

    public function register(string $type, NodeExecutorInterface $executor): void
    {
        $this->executors[$type] = $executor;
    }

    public function execute(string $type, array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        if (! isset($this->executors[$type])) {
            throw new InvalidArgumentException("No executor registered for node type: {$type}");
        }

        return $this->executors[$type]->execute($nodeConfig, $state, $context);
    }
}
