<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\MaxLoopIterationsException;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use NeuronAI\Workflow\WorkflowState;

class LoopNodeExecutor implements NodeExecutorInterface
{
    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $nodeId = (string) ($nodeConfig['id'] ?? 'loop');
        $data = $nodeConfig['data'] ?? [];
        $iterationKey = "__loop_iterations.{$nodeId}";
        $iterations = (int) $state->get($iterationKey, 0) + 1;
        $maxSteps = $this->resolveMaxSteps($data);

        $state->set($iterationKey, $iterations);
        $this->trackLoopIterations($state, $nodeId, $iterations);

        if ($state instanceof BuilderWorkflowState) {
            $state->emitStep('loop_iteration', [
                'node_id' => $nodeId,
                'iteration' => $iterations,
                'max_steps' => $maxSteps,
            ]);
        }

        if ($iterations > $maxSteps) {
            throw new MaxLoopIterationsException($nodeId, $iterations, $maxSteps);
        }

        if ($this->conditionMet($data, $state)) {
            return 'exit';
        }

        return 'continue';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveMaxSteps(array $data): int
    {
        if (isset($data['max_steps'])) {
            return max(1, (int) $data['max_steps']);
        }

        return max(1, (int) config('neuronai-studio.loop.default_max_steps', 10));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function conditionMet(array $data, WorkflowState $state): bool
    {
        $key = $data['state_key'] ?? 'input';
        $operator = $data['operator'] ?? 'not_empty';
        $value = $data['value'] ?? null;
        $stateValue = $state->get($key);

        return match ($operator) {
            'equals' => $stateValue == $value,
            'not_equals' => $stateValue != $value,
            'contains' => is_string($stateValue) && str_contains($stateValue, (string) $value),
            'empty' => empty($stateValue),
            default => ! empty($stateValue),
        };
    }

    protected function trackLoopIterations(WorkflowState $state, string $nodeId, int $iterations): void
    {
        $all = $state->get('__loop_iterations', []);

        if (! is_array($all)) {
            $all = [];
        }

        $all[$nodeId] = $iterations;
        $state->set('__loop_iterations', $all);
    }
}
