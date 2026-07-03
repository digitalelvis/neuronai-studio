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
        $return = $context->returnStatement($nodePlan['returnType']);

        $messageSetup = <<<PHP
        \$template = {$prompt};
        \$prompt = {$context->interpolate('$template')};
        if (\$prompt === '' && \$state->has('input')) {
            \$prompt = (string) \$state->get('input');
        }

        \$attachments = is_array(\$state->get('attachments')) ? \$state->get('attachments') : [];
        \$userMessage = app(MessageFactory::class)->resolveMessageWithAttachments(\$prompt, \$attachments);
PHP;

        if ($data['structured'] ?? false) {
            $outputClass = (string) ($data['output_class'] ?? '');
            $shortClass = class_basename($outputClass);
            $instructions = var_export((string) ($data['instructions'] ?? 'Extract structured data from the user message.'), true);

            $body = <<<PHP
        {$messageSetup}

        \$result = app(AgentRunner::class)->structuredInline([
            'provider' => {$this->exportConfigValue($provider)},
            'model' => {$this->exportConfigValue($model)},
            'instructions' => {$instructions},
        ], \$userMessage, {$shortClass}::class);

        \$state->set({$outputKey}, \$result->structured);

        {$return}
PHP;

            return [
                'body' => $body,
                'imports' => array_values(array_filter([
                    'DigitalElvis\\NeuronAIStudio\\Runtime\\MessageFactory',
                    'DigitalElvis\\NeuronAIStudio\\Runtime\\AgentRunner',
                    $outputClass !== '' ? $outputClass : null,
                ])),
            ];
        }

        $providerExpr = $context->providerExpression($provider, $model);

        $body = <<<PHP
        {$messageSetup}

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

    protected function exportConfigValue(string $value): string
    {
        return var_export($value, true);
    }
}
