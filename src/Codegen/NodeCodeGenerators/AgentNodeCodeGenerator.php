<?php

namespace DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators;

class AgentNodeCodeGenerator implements NodeCodeGeneratorInterface
{
    public function supports(string $type): bool
    {
        return $type === 'agent';
    }

    public function generate(array $nodePlan, CodegenContext $context): array
    {
        $data = $nodePlan['data'];
        $outputKey = var_export((string) ($data['output_key'] ?? 'agent_response'), true);
        $message = var_export((string) ($data['message'] ?? ''), true);
        $return = $context->returnStatement($nodePlan['returnType']);
        $structured = $data['structured'] ?? false;
        $outputClass = (string) ($data['output_class'] ?? '');
        $shortClass = class_basename($outputClass);

        $messageSetup = <<<PHP
        \$template = {$message};
        \$message = {$context->interpolate('$template')};
        if (\$message === '') {
            \$message = (string) \$state->get('input', '');
        }

        \$attachments = is_array(\$state->get('attachments')) ? \$state->get('attachments') : [];
        \$userMessage = app(MessageFactory::class)->resolveMessageWithAttachments(\$message, \$attachments);
PHP;

        if (isset($data['agent_id'])) {
            $agentId = (int) $data['agent_id'];
            $threadKey = 'is_string($state->get(\'__studio_thread_id\')) ? $state->get(\'__studio_thread_id\') : null';

            if ($structured) {
                $body = <<<PHP
        {$messageSetup}

        \$agent = AgentDefinition::findOrFail({$agentId});
        \$response = app(AgentRunner::class)->structuredInline([
            'provider' => \$agent->provider,
            'model' => \$agent->model,
            'instructions' => \$agent->instructions,
            'tools' => \$agent->tools ?? [],
        ], \$userMessage, {$shortClass}::class, \$agent, {$threadKey});

        \$state->set({$outputKey}, \$response->structured);

        {$return}
PHP;
            } else {
                $approvalLine = $this->approvalConfigLine($data, hasDefinition: true);
                $toolControlLine = $this->toolControlConfigLine($data, hasDefinition: true);
                $memoryLine = $this->memoryConfigLine($data, hasDefinition: true);
                $body = <<<PHP
        {$messageSetup}

        \$agent = AgentDefinition::findOrFail({$agentId});
        \$response = app(AgentRunner::class)->runInline([
            'provider' => \$agent->provider,
            'model' => \$agent->model,
            'instructions' => \$agent->instructions,
            'tools' => \$agent->tools ?? [],{$approvalLine}{$toolControlLine}{$memoryLine}
        ], \$userMessage, \$agent, {$threadKey});

        \$state->set({$outputKey}, \$response->content);

        {$return}
PHP;
            }

            return [
                'body' => $body,
                'imports' => array_values(array_filter([
                    'DigitalElvis\\NeuronAIStudio\\Models\\AgentDefinition',
                    'DigitalElvis\\NeuronAIStudio\\Runtime\\AgentRunner',
                    'DigitalElvis\\NeuronAIStudio\\Runtime\\MessageFactory',
                    $structured && $outputClass !== '' ? $outputClass : null,
                ])),
            ];
        }

        $provider = (string) ($data['provider'] ?? config('neuronai-studio.default_provider', 'openai'));
        $model = (string) ($data['model'] ?? config('neuronai-studio.default_model', 'gpt-4o-mini'));
        $instructions = var_export((string) ($data['instructions'] ?? ''), true);
        $providerExpr = $context->providerExpression($provider, $model);

        if ($structured) {
            $body = <<<PHP
        {$messageSetup}

        \$response = app(AgentRunner::class)->structuredInline([
            'provider' => {$this->exportConfigValue($provider)},
            'model' => {$this->exportConfigValue($model)},
            'instructions' => {$instructions},
        ], \$userMessage, {$shortClass}::class);

        \$state->set({$outputKey}, \$response->structured);

        {$return}
PHP;

            return [
                'body' => $body,
                'imports' => array_values(array_filter([
                    'DigitalElvis\\NeuronAIStudio\\Runtime\\AgentRunner',
                    'DigitalElvis\\NeuronAIStudio\\Runtime\\MessageFactory',
                    $outputClass !== '' ? $outputClass : null,
                ])),
            ];
        }

        $requireApproval = (bool) ($data['require_tool_approval'] ?? false);
        $approvalSetup = $requireApproval
            ? "\n        \$agent->addGlobalMiddleware(new ToolApproval());\n"
            : '';

        $body = <<<PHP
        {$messageSetup}

        \$agent = Agent::make()
            ->setProvider({$providerExpr})
            ->addSystemTip({$instructions});
{$approvalSetup}
        \$response = \$agent->chat(\$userMessage);
        \$state->set({$outputKey}, \$response->getContent());

        {$return}
PHP;

        return [
            'body' => $body,
            'imports' => array_values(array_filter([
                'NeuronAI\\Agent',
                'DigitalElvis\\NeuronAIStudio\\Runtime\\MessageFactory',
                $requireApproval ? 'NeuronAI\\Agent\\Middleware\\ToolApproval' : null,
            ])),
        ];
    }

    protected function exportConfigValue(string $value): string
    {
        return var_export($value, true);
    }

    /**
     * Build the `require_tool_approval` config entry for `runInline`.
     *
     * When the node carries an explicit override we emit a literal; otherwise
     * the flag is read from the resolved AgentDefinition at runtime so the
     * generated code honours per-agent approval settings.
     *
     * @param  array<string, mixed>  $data
     */
    protected function approvalConfigLine(array $data, bool $hasDefinition): string
    {
        if (array_key_exists('require_tool_approval', $data)) {
            return "\n            'require_tool_approval' => ".var_export((bool) $data['require_tool_approval'], true).',';
        }

        if ($hasDefinition) {
            return "\n            'require_tool_approval' => (bool) \$agent->require_tool_approval,";
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function toolControlConfigLine(array $data, bool $hasDefinition): string
    {
        $lines = '';

        if (array_key_exists('tool_max_runs', $data) && $data['tool_max_runs'] !== null && $data['tool_max_runs'] !== '') {
            $lines .= "\n            'tool_max_runs' => ".(int) $data['tool_max_runs'].',';
        } elseif ($hasDefinition) {
            $lines .= "\n            'tool_max_runs' => \$agent->tool_max_runs,";
        }

        if (array_key_exists('parallel_tool_calls', $data) && $data['parallel_tool_calls'] !== null && $data['parallel_tool_calls'] !== '') {
            $lines .= "\n            'parallel_tool_calls' => ".var_export((bool) $data['parallel_tool_calls'], true).',';
        } elseif ($hasDefinition) {
            $lines .= "\n            'parallel_tool_calls' => \$agent->parallel_tool_calls,";
        }

        return $lines;
    }

    /**
     * Emit expressible memory overrides; agent envelope is applied at runtime via AgentRunner.
     *
     * @param  array<string, mixed>  $data
     */
    protected function memoryConfigLine(array $data, bool $hasDefinition): string
    {
        $keys = [
            'context_window' => 'int',
            'driver' => 'string',
            'summarization_enabled' => 'bool',
            'summarization_threshold' => 'float',
        ];

        $lines = '';
        $emitted = false;

        foreach ($keys as $key => $type) {
            if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
                continue;
            }

            $value = match ($type) {
                'int' => (int) $data[$key],
                'bool' => var_export((bool) $data[$key], true),
                'float' => (float) $data[$key],
                default => var_export((string) $data[$key], true),
            };

            $lines .= "\n            '{$key}' => {$value},";
            $emitted = true;
        }

        if (! $emitted && $hasDefinition) {
            // AgentDefinition.memory_config is read by AgentRunner::resolveMemoryConfig at runtime.
            $lines .= "\n            // memory_config: inherited from AgentDefinition at runtime";
        }

        return $lines;
    }
}
