<?php

namespace DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators;

class MediaNodeCodeGenerator implements NodeCodeGeneratorInterface
{
    public function supports(string $type): bool
    {
        return in_array($type, ['image', 'speech', 'transcribe', 'video'], true);
    }

    public function generate(array $nodePlan, CodegenContext $context): array
    {
        $data = $nodePlan['data'];
        $type = (string) ($nodePlan['type'] ?? '');
        $prompt = var_export((string) ($data['prompt'] ?? ''), true);
        $return = $context->returnStatement($nodePlan['returnType']);
        $node = var_export([
            'type' => $type,
            'data' => [
                'provider' => (string) ($data['provider'] ?? ''),
                'model' => (string) ($data['model'] ?? ''),
                'output_key' => (string) ($data['output_key'] ?? $type.'_result'),
                'voice' => (string) ($data['voice'] ?? ''),
                'language' => (string) ($data['language'] ?? 'en'),
                'aspect_ratio' => (string) ($data['aspect_ratio'] ?? ''),
                'duration_seconds' => (int) ($data['duration_seconds'] ?? 0),
                'output_format' => (string) ($data['output_format'] ?? 'png'),
                'api_key' => is_string($data['api_key'] ?? null) ? $data['api_key'] : '',
            ],
        ], true);

        $body = <<<PHP
        \$template = {$prompt};
        \$prompt = {$context->interpolate('$template')};
        \$node = {$node};
        \$node['data']['prompt'] = \$prompt;
        app(\\DigitalElvis\\NeuronAIStudio\\Runtime\\NodeExecutors\\MediaNodeExecutor::class)
            ->execute(\$node, \$state, new \\DigitalElvis\\NeuronAIStudio\\Runtime\\GraphContext([], []));

        {$return}
PHP;

        return ['body' => $body, 'imports' => []];
    }
}
