<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\WorkflowExecutionException;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use NeuronAI\Workflow\WorkflowState;
use RuntimeException;

class RunWorkflowNodeExecutor implements NodeExecutorInterface
{
    public const MAX_NESTING_DEPTH = 3;

    public function __construct(
        protected WorkflowRunner $workflowRunner,
    ) {}

    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = is_array($nodeConfig['data'] ?? null) ? $nodeConfig['data'] : [];
        $definition = $this->resolveDefinition($data);
        $nextDepth = $this->assertNestingDepth($state);

        $rawMessage = array_key_exists('message', $data)
            ? (string) $data['message']
            : (string) $state->get('input', '');

        if ($rawMessage === '') {
            $rawMessage = (string) $state->get('input', '');
        }

        $message = StateTemplateInterpolator::interpolate($rawMessage, $state);
        $childState = $this->resolveStateMap($data['state_map'] ?? null, $state);
        $outputKey = is_string($data['output_key'] ?? null) && $data['output_key'] !== ''
            ? $data['output_key']
            : 'child_output';

        $parentRun = $this->resolveParentRun($state);

        try {
            $childRun = $this->workflowRunner->run(
                $definition,
                [
                    'message' => $message,
                    'input' => $message,
                    'state' => $childState,
                    WorkflowRunner::NESTING_DEPTH_INPUT_KEY => $nextDepth,
                ],
                parentRun: $parentRun,
            );
        } catch (WorkflowExecutionException $exception) {
            throw new RuntimeException(
                sprintf('Nested workflow [%s] failed: %s', $definition->slug, $exception->getMessage()),
                0,
                $exception,
            );
        }

        $this->assertChildSucceeded($childRun, $definition);

        $state->set($outputKey, $this->serializeOutput($childRun->output, $data));

        return 'default';
    }

    /** @param  array<string, mixed>  $data */
    protected function resolveDefinition(array $data): WorkflowDefinition
    {
        $workflowId = $data['workflow_id'] ?? null;
        if ($workflowId === null || $workflowId === '') {
            throw new RuntimeException('run_workflow node requires data.workflow_id.');
        }

        $definition = WorkflowDefinition::query()->find($workflowId);
        if ($definition === null) {
            throw new RuntimeException(sprintf('run_workflow target workflow [%s] not found.', $workflowId));
        }

        return $definition;
    }

    protected function assertNestingDepth(WorkflowState $state): int
    {
        $current = (int) ($state->get(WorkflowRunner::NESTING_DEPTH_INPUT_KEY, 0) ?? 0);
        $next = $current + 1;

        if ($next > self::MAX_NESTING_DEPTH) {
            throw new RuntimeException(sprintf(
                'run_workflow nesting depth would exceed %d (max %d).',
                $next,
                self::MAX_NESTING_DEPTH,
            ));
        }

        return $next;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveStateMap(mixed $stateMap, WorkflowState $state): array
    {
        if (! is_array($stateMap)) {
            return [];
        }

        $resolved = [];

        foreach ($stateMap as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = isset($row['key']) && is_string($row['key']) ? trim($row['key']) : '';
            if ($key === '') {
                continue;
            }

            $value = $row['value'] ?? null;
            if (is_string($value)) {
                $value = StateTemplateInterpolator::interpolate($value, $state);
            }

            $resolved[$key] = $value;
        }

        return $resolved;
    }

    protected function resolveParentRun(WorkflowState $state): ?StudioRun
    {
        $parentId = $state->get('__studio_run_id');
        if (! is_string($parentId) || $parentId === '') {
            return null;
        }

        return StudioRun::query()->find($parentId);
    }

    protected function assertChildSucceeded(StudioRun $childRun, WorkflowDefinition $definition): void
    {
        if (in_array($childRun->status, ['awaiting_input', 'awaiting_tool_approval'], true)) {
            throw new RuntimeException(sprintf(
                'Nested workflow [%s] requested a human interrupt, which is not supported in v1.',
                $definition->slug,
            ));
        }

        if ($childRun->status !== 'completed') {
            throw new RuntimeException(sprintf(
                'Nested workflow [%s] ended with status [%s].',
                $definition->slug,
                $childRun->status,
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $nodeData
     */
    protected function serializeOutput(mixed $output, array $nodeData = []): string
    {
        $mode = is_string($nodeData['output_mode'] ?? null) ? $nodeData['output_mode'] : 'reply';

        if (is_string($output)) {
            return $output;
        }

        if ($output === null) {
            return '';
        }

        if (! is_array($output)) {
            return json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        if ($mode === 'state') {
            return json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        if (array_key_exists(\DigitalElvis\NeuronAIStudio\Runtime\WorkflowReplyResolver::STATE_KEY, $output)) {
            return app(\DigitalElvis\NeuronAIStudio\Runtime\WorkflowReplyResolver::class)
                ->textFromOutput($output);
        }

        return json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}
