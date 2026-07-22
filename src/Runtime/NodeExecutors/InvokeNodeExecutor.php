<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use NeuronAI\Workflow\WorkflowState;
use RuntimeException;

class InvokeNodeExecutor implements NodeExecutorInterface
{
    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = $nodeConfig['data'] ?? [];
        $outputKey = is_string($data['output_key'] ?? null) && $data['output_key'] !== ''
            ? $data['output_key']
            : 'invoke_result';
        $hookClass = $this->normalizeClass($data['hook_class'] ?? null);

        if ($hookClass === null) {
            throw new RuntimeException('Invoke node requires data.hook_class (FQCN).');
        }

        if (! $this->isAllowlisted($hookClass)) {
            throw new RuntimeException(
                "Invoke hook [{$hookClass}] is not in config('neuronai-studio.invoke_hooks')."
            );
        }

        if (! class_exists($hookClass)) {
            throw new RuntimeException("Invoke hook class [{$hookClass}] does not exist.");
        }

        if (! method_exists($hookClass, '__invoke')) {
            throw new RuntimeException("Invoke hook [{$hookClass}] must implement __invoke().");
        }

        $hook = app($hookClass);

        if (! is_callable($hook)) {
            throw new RuntimeException("Invoke hook [{$hookClass}] is not callable.");
        }

        $state->set($outputKey, $hook($state));

        return 'default';
    }

    public function isAllowlisted(string $hookClass): bool
    {
        $allowed = config('neuronai-studio.invoke_hooks', []);

        if (! is_array($allowed) || $allowed === []) {
            return false;
        }

        $normalized = $this->normalizeClass($hookClass);

        foreach ($allowed as $entry) {
            if ($this->normalizeClass($entry) === $normalized) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeClass(mixed $class): ?string
    {
        if (! is_string($class)) {
            return null;
        }

        $class = ltrim(trim($class), '\\');

        return $class !== '' ? $class : null;
    }
}
