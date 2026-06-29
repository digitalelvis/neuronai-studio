<?php

namespace ElvisLopesDigital\NeuronAIStudio\Codegen\NodeCodeGenerators;

class McpNodeCodeGenerator implements NodeCodeGeneratorInterface
{
    public function supports(string $type): bool
    {
        return $type === 'mcp';
    }

    public function generate(array $nodePlan, CodegenContext $context): array
    {
        $data = $nodePlan['data'];
        $outputKey = var_export((string) ($data['output_key'] ?? 'mcp_result'), true);
        $serverSlug = var_export((string) ($data['mcp_server'] ?? ''), true);
        $toolName = var_export((string) ($data['tool_name'] ?? ''), true);
        $parameters = $context->exporter->exportArray(is_array($data['parameters'] ?? null) ? $data['parameters'] : [], 3);
        $return = $context->returnStatement($nodePlan['returnType']);

        $body = <<<PHP
        try {
            \$parameters = [];
            foreach ({$parameters} as \$key => \$value) {
                \$parameters[\$key] = is_string(\$value) && str_starts_with(\$value, '$')
                    ? \$state->get(ltrim(\$value, '$'))
                    : \$value;
            }

            \$tools = app(McpToolResolver::class)->toolsForBinding({$serverSlug}, [
                'only' => [{$toolName}],
            ]);

            if (\$tools === []) {
                throw new \\RuntimeException('MCP tool was not found.');
            }

            \$tool = \$tools[0];
            \$tool->setInputs(\$parameters);
            \$tool->execute();

            \$state->set({$outputKey}, [
                'server' => {$serverSlug},
                'name' => \$tool->getName(),
                'result' => \$tool->getResult(),
            ]);
        } catch (\\Throwable \$exception) {
            \$state->set({$outputKey}, ['error' => \$exception->getMessage()]);
        }

        {$return}
PHP;

        return [
            'body' => $body,
            'imports' => [
                'ElvisLopesDigital\\NeuronAIStudio\\Runtime\\McpToolResolver',
            ],
        ];
    }
}
