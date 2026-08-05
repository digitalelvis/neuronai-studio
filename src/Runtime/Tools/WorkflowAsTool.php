<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Tools;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\WorkflowExecutionException;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\RunWorkflowNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Workflow\WorkflowState;
use RuntimeException;
use Throwable;

final class WorkflowAsTool extends Tool implements ToolContextAware
{
    use InteractsWithToolContext;

    /**
     * @param  array<string, mixed>  $nodeData  run_workflow node `data`
     * @param  array<string, mixed>  $parentState  Parent workflow state snapshot for templates
     */
    public function __construct(
        string $slug,
        string $description,
        protected array $nodeData,
        protected ?string $parentRunId = null,
        protected string $inputDescription = 'Message / task for the child workflow',
        protected array $parentState = [],
        protected int $nestingDepth = 0,
    ) {
        parent::__construct(name: $slug, description: $description);
    }

    protected function properties(): array
    {
        return [
            ToolProperty::make(
                name: 'input',
                type: PropertyType::STRING,
                description: $this->inputDescription,
                required: true,
            ),
        ];
    }

    public function __invoke(string $input): string
    {
        try {
            return $this->runChild($input);
        } catch (Throwable $exception) {
            return 'Error: '.$exception->getMessage();
        }
    }

    protected function runChild(string $input): string
    {
        $definition = $this->resolveDefinition();
        $nextDepth = $this->assertNestingDepth();
        $parentWorkflowState = new WorkflowState($this->parentState);
        $message = $this->resolveMessage($input, $parentWorkflowState);
        $childState = $this->resolveStateMap($parentWorkflowState);

        if (($toolContext = $this->toolContext()) !== null && ! $toolContext->isEmpty()) {
            $childState = array_merge($toolContext->all(), $childState);
        }

        $parentRun = $this->parentRunId !== null && $this->parentRunId !== ''
            ? StudioRun::query()->find($this->parentRunId)
            : null;

        try {
            $childRun = app(WorkflowRunner::class)->run(
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

        return $this->serializeOutput($childRun->output);
    }

    protected function resolveDefinition(): WorkflowDefinition
    {
        $workflowId = $this->nodeData['workflow_id'] ?? null;
        if ($workflowId === null || $workflowId === '') {
            throw new RuntimeException('run_workflow tool requires data.workflow_id.');
        }

        $definition = WorkflowDefinition::query()->find($workflowId);
        if ($definition === null) {
            throw new RuntimeException(sprintf('run_workflow target workflow [%s] not found.', $workflowId));
        }

        return $definition;
    }

    protected function assertNestingDepth(): int
    {
        $next = $this->nestingDepth + 1;

        if ($next > RunWorkflowNodeExecutor::MAX_NESTING_DEPTH) {
            throw new RuntimeException(sprintf(
                'run_workflow nesting depth would exceed %d (max %d).',
                $next,
                RunWorkflowNodeExecutor::MAX_NESTING_DEPTH,
            ));
        }

        return $next;
    }

    protected function resolveMessage(string $callerInput, WorkflowState $parentState): string
    {
        $caller = trim($callerInput);
        if ($caller !== '') {
            return $caller;
        }

        $rawMessage = (string) ($this->nodeData['message'] ?? '');
        if ($rawMessage === '') {
            return '';
        }

        return StateTemplateInterpolator::interpolate($rawMessage, $parentState);
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveStateMap(WorkflowState $parentState): array
    {
        $stateMap = $this->nodeData['state_map'] ?? null;
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
                $value = StateTemplateInterpolator::interpolate($value, $parentState);
            }

            $resolved[$key] = $value;
        }

        return $resolved;
    }

    protected function serializeOutput(mixed $output): string
    {
        if (is_string($output)) {
            return $output;
        }

        if ($output === null) {
            return '';
        }

        return json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}
