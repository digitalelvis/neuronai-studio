<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime\NodeExecutors;

use ElvisLopesDigital\NeuronAIStudio\Runtime\GraphContext;
use ElvisLopesDigital\NeuronAIStudio\Runtime\ToolResolver;
use NeuronAI\Tools\ToolInterface;
use NeuronAI\Workflow\WorkflowState;

class ToolNodeExecutor implements NodeExecutorInterface
{
    public function __construct(
        protected ToolResolver $toolResolver,
    ) {}

    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = $nodeConfig['data'] ?? [];
        $outputKey = $data['output_key'] ?? 'tool_result';

        try {
            if (! empty($data['tool_ref'])) {
                $parameters = $this->resolveParameters($data, $state);
                $tools = $this->toolResolver->resolve($data['tool_ref'], [
                    'config' => $data['config'] ?? [],
                ]);

                if ($tools === []) {
                    throw new \RuntimeException('Tool reference could not be resolved.');
                }

                $tool = $tools[0];

                if ($tool instanceof ToolInterface) {
                    $tool->setInputs($parameters);
                    $tool->execute();
                    $state->set($outputKey, [
                        'name' => $tool->getName(),
                        'result' => $tool->getResult(),
                    ]);

                    return 'default';
                }

                $state->set($outputKey, ['error' => 'Resolved reference is not an executable tool.']);
            }

            $toolClass = $data['tool_class'] ?? null;

            if ($toolClass && class_exists($toolClass)) {
                $tool = app($toolClass);
                $result = method_exists($tool, 'execute')
                    ? $tool->execute($this->resolveParameters($data, $state))
                    : ['status' => 'executed', 'tool' => $toolClass];
                $state->set($outputKey, $result);

                return 'default';
            }

            $state->set($outputKey, ['error' => 'Tool not configured. Set tool_ref or tool_class.']);
        } catch (\Throwable $exception) {
            $state->set($outputKey, ['error' => $exception->getMessage()]);
        }

        return 'default';
    }

    /** @return array<string, mixed> */
    protected function resolveParameters(array $data, WorkflowState $state): array
    {
        $parameters = $data['parameters'] ?? [];

        if (! is_array($parameters)) {
            return [];
        }

        $resolved = [];

        foreach ($parameters as $key => $value) {
            if (is_string($value) && str_starts_with($value, '$')) {
                $resolved[$key] = $state->get(ltrim($value, '$'));
            } else {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }
}
