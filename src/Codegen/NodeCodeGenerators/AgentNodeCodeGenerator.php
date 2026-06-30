<?php

namespace ElvisLopesDigital\NeuronAIStudio\Codegen\NodeCodeGenerators;

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

        if (isset($data['agent_id'])) {
            $agentId = (int) $data['agent_id'];
            $body = <<<PHP
        \$template = {$message};
        \$message = {$context->interpolate('$template')};
        if (\$message === '') {
            \$message = (string) \$state->get('input', '');
        }

        \$attachments = is_array(\$state->get('attachments')) ? \$state->get('attachments') : [];
        \$userMessage = app(MessageFactory::class)->resolveMessageWithAttachments(\$message, \$attachments);

        \$agent = AgentDefinition::findOrFail({$agentId});
        \$response = app(AgentRunner::class)->runInline([
            'provider' => \$agent->provider,
            'model' => \$agent->model,
            'instructions' => \$agent->instructions,
            'tools' => \$agent->tools ?? [],
        ], \$userMessage, \$agent, is_string(\$state->get('__studio_thread_id')) ? \$state->get('__studio_thread_id') : null);

        \$state->set({$outputKey}, \$response->content);

        {$return}
PHP;

            return [
                'body' => $body,
                'imports' => [
                    'ElvisLopesDigital\\NeuronAIStudio\\Models\\AgentDefinition',
                    'ElvisLopesDigital\\NeuronAIStudio\\Runtime\\AgentRunner',
                    'ElvisLopesDigital\\NeuronAIStudio\\Runtime\\MessageFactory',
                ],
            ];
        }

        $provider = (string) ($data['provider'] ?? config('neuronai-studio.default_provider', 'openai'));
        $model = (string) ($data['model'] ?? config('neuronai-studio.default_model', 'gpt-4o-mini'));
        $instructions = var_export((string) ($data['instructions'] ?? ''), true);
        $providerExpr = $context->providerExpression($provider, $model);

        $body = <<<PHP
        \$template = {$message};
        \$message = {$context->interpolate('$template')};
        if (\$message === '') {
            \$message = (string) \$state->get('input', '');
        }

        \$attachments = is_array(\$state->get('attachments')) ? \$state->get('attachments') : [];
        \$userMessage = app(MessageFactory::class)->resolveMessageWithAttachments(\$message, \$attachments);

        \$agent = Agent::make()
            ->setProvider({$providerExpr})
            ->addSystemTip({$instructions});

        \$response = \$agent->chat(\$userMessage);
        \$state->set({$outputKey}, \$response->getContent());

        {$return}
PHP;

        return [
            'body' => $body,
            'imports' => [
                'NeuronAI\\Agent',
                'ElvisLopesDigital\\NeuronAIStudio\\Runtime\\MessageFactory',
            ],
        ];
    }
}
