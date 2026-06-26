<?php

namespace ElvisLopesDigital\NeuronAIStudio\Codegen\NodeCodeGenerators;

class SetStateNodeCodeGenerator implements NodeCodeGeneratorInterface
{
    public function supports(string $type): bool
    {
        return $type === 'set_state';
    }

    public function generate(array $nodePlan, CodegenContext $context): array
    {
        $data = $nodePlan['data'];
        $key = var_export((string) ($data['key'] ?? 'value'), true);
        $return = $context->returnStatement($nodePlan['returnType']);

        if (($data['from_key'] ?? null) !== null) {
            $fromKey = var_export((string) $data['from_key'], true);
            $body = <<<PHP
        \$state->set({$key}, \$state->get({$fromKey}));
        {$return}
PHP;
        } else {
            $value = $context->exporter->exportValue($data['value'] ?? null, 2);
            $body = <<<PHP
        \$state->set({$key}, {$value});
        {$return}
PHP;
        }

        return ['body' => $body, 'imports' => []];
    }
}
