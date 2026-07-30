<?php

namespace DigitalElvis\NeuronAIStudio\McpServer;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\McpEndpoint;
use DigitalElvis\NeuronAIStudio\Models\McpEndpointBinding;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use NeuronAI\Tools\ToolInterface;
use NeuronAI\Tools\Toolkits\AbstractToolkit;
use NeuronAI\Tools\Toolkits\ToolkitInterface;

class McpToolInvoker
{
    public function __construct(
        protected McpToolCatalog $catalog,
        protected ToolResolver $toolResolver,
        protected AgentRunner $agentRunner,
        protected WorkflowRunner $workflowRunner,
        protected McpInvocationRecorder $recorder,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{content: array<int, array{type: string, text: string}>, isError?: bool}
     */
    public function invoke(McpEndpoint $endpoint, string $toolName, array $arguments = []): array
    {
        $tools = $this->catalog->toolsByName($endpoint);
        $tool = $tools[$toolName] ?? null;

        if ($tool === null) {
            return $this->error("Unknown tool [{$toolName}].");
        }

        $timeout = max(1, (int) $endpoint->timeout_seconds);
        if (function_exists('set_time_limit')) {
            @set_time_limit($timeout);
        }

        try {
            $source = $tool['source'];
            $result = match ($source['kind'] ?? '') {
                McpEndpointBinding::KIND_TOOL => $this->invokeTool($source, $arguments),
                McpEndpointBinding::KIND_TOOLKIT => $this->invokeToolkitChild($source, $arguments),
                McpEndpointBinding::KIND_AGENT => $this->invokeAgent($source, $arguments),
                McpEndpointBinding::KIND_WORKFLOW => $this->invokeWorkflow($source, $arguments),
                default => throw new \RuntimeException('Unsupported tool binding kind.'),
            };

            $kind = (string) ($source['kind'] ?? '');
            if (in_array($kind, [McpEndpointBinding::KIND_TOOL, McpEndpointBinding::KIND_TOOLKIT], true)) {
                $this->recorder->record(
                    $endpoint,
                    $toolName,
                    $arguments,
                    ['result' => $result],
                );
            }

            return [
                'content' => [
                    ['type' => 'text', 'text' => $this->stringify($result)],
                ],
            ];
        } catch (\Throwable $exception) {
            $kind = (string) (($tool['source']['kind'] ?? '') ?: '');
            if (in_array($kind, [McpEndpointBinding::KIND_TOOL, McpEndpointBinding::KIND_TOOLKIT], true)) {
                $this->recorder->record(
                    $endpoint,
                    $toolName,
                    $arguments,
                    null,
                    $exception->getMessage(),
                    'failed',
                );
            }

            return $this->error($exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $arguments
     */
    protected function invokeTool(array $source, array $arguments): mixed
    {
        $tools = $this->toolResolver->resolve((string) $source['ref']);

        if ($tools === [] || ! ($tools[0] instanceof ToolInterface)) {
            throw new \RuntimeException('Tool reference could not be resolved to an executable tool.');
        }

        /** @var ToolInterface $tool */
        $tool = $tools[0];
        $tool->setInputs($arguments);
        $tool->execute();

        return $tool->getResult();
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $arguments
     */
    protected function invokeToolkitChild(array $source, array $arguments): mixed
    {
        // Neuron AbstractToolkit::only/exclude filter by class-string, not tool name.
        // Catalog already filtered by name for tools/list; invoke by child name on the full toolkit.
        $resolved = $this->toolResolver->resolve((string) $source['ref']);

        if ($resolved === [] || ! ($resolved[0] instanceof ToolkitInterface)) {
            throw new \RuntimeException('Toolkit reference could not be resolved.');
        }

        /** @var ToolkitInterface $toolkit */
        $toolkit = $resolved[0];
        $childName = (string) ($source['child_tool'] ?? '');

        $children = $toolkit instanceof AbstractToolkit
            ? $toolkit->tools()
            : ($toolkit->tools() ?? []);

        foreach ($children as $child) {
            if (! $child instanceof ToolInterface) {
                continue;
            }

            if ($child->getName() !== $childName) {
                continue;
            }

            $child->setInputs($arguments);
            $child->execute();

            return $child->getResult();
        }

        throw new \RuntimeException("Toolkit tool [{$childName}] was not found.");
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $arguments
     */
    protected function invokeAgent(array $source, array $arguments): mixed
    {
        $agentId = (int) ($source['agent_id'] ?? $source['ref'] ?? 0);
        $agent = AgentDefinition::find($agentId);

        if (! $agent) {
            throw new \RuntimeException("Agent [{$agentId}] not found.");
        }

        if ($agent->require_tool_approval) {
            throw new \RuntimeException(
                "Agent [{$agent->slug}] requires tool approval and cannot be invoked via MCP in this version."
            );
        }

        $message = (string) ($arguments['message'] ?? '');
        if (trim($message) === '') {
            throw new \InvalidArgumentException('Argument [message] is required.');
        }

        $result = $this->agentRunner->run($agent, $message);

        return [
            'content' => $result->content,
            'run_id' => $result->runId,
            'tool_events' => $result->toolEvents,
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $arguments
     */
    protected function invokeWorkflow(array $source, array $arguments): mixed
    {
        $workflowId = (int) ($source['workflow_id'] ?? $source['ref'] ?? 0);
        $workflow = WorkflowDefinition::find($workflowId);

        if (! $workflow) {
            throw new \RuntimeException("Workflow [{$workflowId}] not found.");
        }

        $input = [];
        if (isset($arguments['input']) && is_array($arguments['input'])) {
            $input = $arguments['input'];
        }

        foreach ($arguments as $key => $value) {
            if ($key === 'input') {
                continue;
            }
            $input[$key] = $value;
        }

        $run = $this->workflowRunner->run($workflow, $input);

        return [
            'run_id' => $run->id,
            'status' => $run->status,
            'output' => $run->output,
            'error_message' => $run->error_message,
        ];
    }

    protected function stringify(mixed $result): string
    {
        if (is_string($result)) {
            return $result;
        }

        if ($result === null) {
            return '';
        }

        $encoded = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? (string) $result : $encoded;
    }

    /**
     * @return array{content: array<int, array{type: string, text: string}>, isError: bool}
     */
    protected function error(string $message): array
    {
        return [
            'content' => [
                ['type' => 'text', 'text' => $message],
            ],
            'isError' => true,
        ];
    }
}
