<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Tools;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use Throwable;

final class NodeAsTool extends Tool
{
    /**
     * @param  array<string, mixed>  $agentConfig  Specialist node `data` (inline or existing).
     */
    public function __construct(
        string $slug,
        string $description,
        protected array $agentConfig,
        protected ?string $parentRunId = null,
        protected string $inputDescription = 'Task for the specialist',
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
            $runner = app(AgentRunner::class);
            $definition = $this->resolveDefinition();
            $parentRun = $this->parentRunId !== null && $this->parentRunId !== ''
                ? StudioRun::query()->find($this->parentRunId)
                : null;

            $result = $runner->runInline(
                $this->buildRunnerConfig($definition),
                $input,
                $definition,
                parentRun: $parentRun,
            );

            return (string) $result->content;
        } catch (Throwable $exception) {
            return 'Error: '.$exception->getMessage();
        }
    }

    protected function resolveDefinition(): ?AgentDefinition
    {
        $mode = (string) ($this->agentConfig['config_mode'] ?? '');
        $agentId = $this->agentConfig['agent_id'] ?? null;

        if ($mode === 'existing' || ($mode === '' && $agentId !== null && $agentId !== '')) {
            if ($agentId === null || $agentId === '') {
                return null;
            }

            return AgentDefinition::query()->find($agentId);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildRunnerConfig(?AgentDefinition $definition): array
    {
        if ($definition !== null) {
            return [
                'provider' => $definition->provider,
                'model' => $definition->model,
                'instructions' => $definition->instructions,
                'tools' => $definition->tools ?? [],
            ];
        }

        return [
            'provider' => $this->agentConfig['provider'] ?? config('neuronai-studio.default_provider'),
            'model' => $this->agentConfig['model'] ?? config('neuronai-studio.default_model'),
            'instructions' => $this->agentConfig['instructions'] ?? 'You are a helpful AI assistant.',
            'tools' => is_array($this->agentConfig['tools'] ?? null) ? $this->agentConfig['tools'] : [],
        ];
    }
}
