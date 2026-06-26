<?php

namespace ElvisLopesDigital\NeuronAIStudio\Codegen\NodeCodeGenerators;

class ToolNodeCodeGenerator implements NodeCodeGeneratorInterface
{
    public function supports(string $type): bool
    {
        return $type === 'tool';
    }

    public function generate(array $nodePlan, CodegenContext $context): array
    {
        $data = $nodePlan['data'];
        $outputKey = var_export((string) ($data['output_key'] ?? 'tool_result'), true);
        $return = $context->returnStatement($nodePlan['returnType']);
        $parameters = $context->exporter->exportArray(is_array($data['parameters'] ?? null) ? $data['parameters'] : [], 3);

        if (! empty($data['tool_ref'])) {
            $toolRef = var_export((string) $data['tool_ref'], true);
            $config = $context->exporter->exportArray(is_array($data['config'] ?? null) ? $data['config'] : [], 3);

            $body = <<<PHP
        \$parameters = [];
        foreach ({$parameters} as \$key => \$value) {
            \$parameters[\$key] = is_string(\$value) && str_starts_with(\$value, '$')
                ? \$state->get(ltrim(\$value, '$'))
                : \$value;
        }

        \$tools = app(ToolResolver::class)->resolve({$toolRef}, ['config' => {$config}]);

        if (\$tools === []) {
            \$state->set({$outputKey}, ['error' => 'Tool reference could not be resolved.']);
        } else {
            \$tool = \$tools[0];
            \$tool->setInputs(\$parameters);
            \$tool->execute();
            \$state->set({$outputKey}, [
                'name' => \$tool->getName(),
                'result' => \$tool->getResult(),
            ]);
        }

        {$return}
PHP;

            return [
                'body' => $body,
                'imports' => [
                    'ElvisLopesDigital\\NeuronAIStudio\\Runtime\\ToolResolver',
                ],
            ];
        }

        $toolClass = var_export((string) ($data['tool_class'] ?? ''), true);

        $body = <<<PHP
        \$parameters = [];
        foreach ({$parameters} as \$key => \$value) {
            \$parameters[\$key] = is_string(\$value) && str_starts_with(\$value, '$')
                ? \$state->get(ltrim(\$value, '$'))
                : \$value;
        }

        if ({$toolClass} !== '' && class_exists({$toolClass})) {
            \$tool = app({$toolClass});
            \$result = method_exists(\$tool, 'execute')
                ? \$tool->execute(\$parameters)
                : ['status' => 'executed', 'tool' => {$toolClass}];
            \$state->set({$outputKey}, \$result);
        } else {
            \$state->set({$outputKey}, ['error' => 'Tool not configured. Set tool_ref or tool_class.']);
        }

        {$return}
PHP;

        return ['body' => $body, 'imports' => []];
    }
}
