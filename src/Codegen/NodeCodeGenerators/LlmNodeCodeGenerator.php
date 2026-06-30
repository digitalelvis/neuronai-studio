<?php

namespace DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators;

class LlmNodeCodeGenerator implements NodeCodeGeneratorInterface
{
    public function supports(string $type): bool
    {
        return $type === 'llm';
    }

    public function generate(array $nodePlan, CodegenContext $context): array
    {
        $data = $nodePlan['data'];
        $provider = (string) ($data['provider'] ?? config('neuronai-studio.default_provider', 'openai'));
        $model = (string) ($data['model'] ?? config('neuronai-studio.default_model', 'gpt-4o-mini'));
        $prompt = var_export((string) ($data['prompt'] ?? ''), true);
        $outputKey = var_export((string) ($data['output_key'] ?? 'llm_response'), true);
        $providerExpr = $context->providerExpression($provider, $model);
        $return = $context->returnStatement($nodePlan['returnType']);

        $body = <<<PHP
        \$template = {$prompt};
        \$prompt = {$context->interpolate('$template')};
        if (\$prompt === '' && \$state->has('input')) {
            \$prompt = (string) \$state->get('input');
        }

        \$attachments = is_array(\$state->get('attachments')) ? \$state->get('attachments') : [];
        \$userMessage = app(MessageFactory::class)->resolveMessageWithAttachments(\$prompt, \$attachments);

        \$aiProvider = {$providerExpr};
        \$response = \$aiProvider->chat(\$userMessage);
        \$state->set({$outputKey}, \$response->getContent());

        {$return}
PHP;

        return [
            'body' => $body,
            'imports' => [
                'DigitalElvis\\NeuronAIStudio\\Runtime\\MessageFactory',
            ],
        ];
    }
}
