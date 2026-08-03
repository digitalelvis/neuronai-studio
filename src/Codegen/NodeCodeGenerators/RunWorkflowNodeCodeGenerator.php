<?php

namespace DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators;

class RunWorkflowNodeCodeGenerator implements NodeCodeGeneratorInterface
{
    public function supports(string $type): bool
    {
        return $type === 'run_workflow';
    }

    public function generate(array $nodePlan, CodegenContext $context): array
    {
        $data = $nodePlan['data'];
        $return = $context->returnStatement($nodePlan['returnType']);
        $outputKey = var_export((string) ($data['output_key'] ?? 'child_output'), true);
        $workflowId = var_export((string) ($data['workflow_id'] ?? ''), true);
        $message = var_export((string) ($data['message'] ?? ''), true);
        $maxDepth = \DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\RunWorkflowNodeExecutor::MAX_NESTING_DEPTH;

        if (($data['workflow_id'] ?? '') === '' || $data['workflow_id'] === null) {
            $body = <<<PHP
        throw new \\RuntimeException('run_workflow node requires data.workflow_id.');

        {$return}
PHP;

            return ['body' => $body, 'imports' => []];
        }

        $stateAssignments = $this->exportStateMapAssignments($data['state_map'] ?? null, $context);

        $body = <<<PHP
        \$template = {$message};
        \$message = {$context->interpolate('$template')};
        if (\$message === '') {
            \$message = (string) \$state->get('input', '');
        }

        \$childState = [];
{$stateAssignments}
        \$nextDepth = (int) (\$state->get(\\DigitalElvis\\NeuronAIStudio\\Runtime\\WorkflowRunner::NESTING_DEPTH_INPUT_KEY, 0) ?? 0) + 1;
        if (\$nextDepth > {$maxDepth}) {
            throw new \\RuntimeException(sprintf('run_workflow nesting depth would exceed %d (max {$maxDepth}).', \$nextDepth));
        }

        \$definition = \\DigitalElvis\\NeuronAIStudio\\Models\\WorkflowDefinition::query()->find({$workflowId});
        if (\$definition === null) {
            throw new \\RuntimeException(sprintf('run_workflow target workflow [%s] not found.', {$workflowId}));
        }

        \$parentRunId = \$state->get('__studio_run_id');
        \$parentRun = is_string(\$parentRunId) && \$parentRunId !== ''
            ? \\DigitalElvis\\NeuronAIStudio\\Models\\StudioRun::query()->find(\$parentRunId)
            : null;

        try {
            \$childRun = app(\\DigitalElvis\\NeuronAIStudio\\Runtime\\WorkflowRunner::class)->run(
                \$definition,
                [
                    'message' => \$message,
                    'input' => \$message,
                    'state' => \$childState,
                    \\DigitalElvis\\NeuronAIStudio\\Runtime\\WorkflowRunner::NESTING_DEPTH_INPUT_KEY => \$nextDepth,
                ],
                parentRun: \$parentRun,
            );
        } catch (\\DigitalElvis\\NeuronAIStudio\\Runtime\\Exceptions\\WorkflowExecutionException \$exception) {
            throw new \\RuntimeException(
                sprintf('Nested workflow [%s] failed: %s', \$definition->slug, \$exception->getMessage()),
                0,
                \$exception,
            );
        }

        if (in_array(\$childRun->status, ['awaiting_input', 'awaiting_tool_approval'], true)) {
            throw new \\RuntimeException(sprintf(
                'Nested workflow [%s] requested a human interrupt, which is not supported in v1.',
                \$definition->slug,
            ));
        }

        if (\$childRun->status !== 'completed') {
            throw new \\RuntimeException(sprintf(
                'Nested workflow [%s] ended with status [%s].',
                \$definition->slug,
                \$childRun->status,
            ));
        }

        \$output = \$childRun->output;
        if (is_string(\$output)) {
            \$serialized = \$output;
        } elseif (\$output === null) {
            \$serialized = '';
        } else {
            \$serialized = json_encode(\$output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        \$state->set({$outputKey}, \$serialized);

        {$return}
PHP;

        return [
            'body' => $body,
            'imports' => [],
        ];
    }

    protected function exportStateMapAssignments(mixed $stateMap, CodegenContext $context): string
    {
        if (! is_array($stateMap) || $stateMap === []) {
            return '';
        }

        $lines = [];

        foreach ($stateMap as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = isset($row['key']) && is_string($row['key']) ? trim($row['key']) : '';
            if ($key === '') {
                continue;
            }

            $keyExport = var_export($key, true);
            $value = $row['value'] ?? null;

            if (is_string($value)) {
                $template = var_export($value, true);
                $lines[] = "        \$childState[{$keyExport}] = {$context->interpolate($template)};";
            } else {
                $lines[] = '        $childState['.$keyExport.'] = '.var_export($value, true).';';
            }
        }

        return $lines === [] ? '' : implode("\n", $lines)."\n";
    }
}
