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
        $visionFlag = array_key_exists('vision', $data)
            ? (($data['vision'] === true) ? 'true' : 'false')
            : 'true';

        $messageSetup = <<<PHP
        \$template = {$prompt};
        \$prompt = {$context->interpolate('$template')};
        if (\$prompt === '' && \$state->has('input')) {
            \$prompt = (string) \$state->get('input');
        }

        \$attachments = app(MessageFactory::class)->resolveAttachmentsForNode(
            ['vision' => {$visionFlag}],
            \$state,
            defaultVision: true,
        );
        \$userMessage = app(MessageFactory::class)->resolveMessageWithAttachments(\$prompt, \$attachments);
PHP;

        $apiKey = $this->resolveApiKeyOverride($data);
        $apiKeyConfigLine = $apiKey !== null
            ? "\n            'api_key' => ".$this->exportConfigValue($apiKey).','
            : '';

        if ($data['structured'] ?? false) {
            $outputClass = (string) ($data['output_class'] ?? '');
            $shortClass = class_basename($outputClass);
            $instructions = var_export((string) ($data['instructions'] ?? 'Extract structured data from the user message.'), true);

            $body = <<<PHP
        {$messageSetup}

        \$result = app(AgentRunner::class)->structuredInline([
            'provider' => {$this->exportConfigValue($provider)},
            'model' => {$this->exportConfigValue($model)},
            'instructions' => {$instructions},{$apiKeyConfigLine}
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

        $providerExpr = $context->providerExpression($provider, $model, $apiKey);

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

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveApiKeyOverride(array $data): ?string
    {
        $apiKey = $data['api_key'] ?? null;

        return is_string($apiKey) && $apiKey !== '' ? $apiKey : null;
    }
}
