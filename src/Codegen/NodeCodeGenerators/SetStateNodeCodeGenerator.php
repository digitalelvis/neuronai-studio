<?php

namespace DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators;

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
        $imports = [];

        if (($data['from_key'] ?? null) !== null && $data['from_key'] !== '') {
            $fromKey = var_export((string) $data['from_key'], true);
            $body = <<<PHP
        \$state->set({$key}, \$state->get({$fromKey}));
        {$return}
PHP;
        } elseif (($data['append_from_key'] ?? null) !== null && $data['append_from_key'] !== '') {
            $appendKey = var_export((string) $data['append_from_key'], true);
            $body = <<<PHP
        \$append = \$state->get({$appendKey});
        \$current = \$state->get({$key}, '');
        \$segments = array_filter([
            is_string(\$current) ? trim(\$current) : (string) \$current,
            is_string(\$append) ? trim(\$append) : (is_scalar(\$append) ? (string) \$append : ''),
        ], fn (string \$segment) => \$segment !== '');
        \$state->set({$key}, implode("\\n", \$segments));
        {$return}
PHP;
        } elseif (is_string($data['value'] ?? null)) {
            $template = var_export((string) $data['value'], true);
            $imports[] = \DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator::class;
            $body = <<<PHP
        \$template = {$template};
        \$state->set({$key}, \\DigitalElvis\\NeuronAIStudio\\Runtime\\StateTemplateInterpolator::interpolate(\$template, \$state));
        {$return}
PHP;
        } else {
            $value = $context->exporter->exportValue($data['value'] ?? null, 2);
            $body = <<<PHP
        \$state->set({$key}, {$value});
        {$return}
PHP;
        }

        return ['body' => $body, 'imports' => $imports];
    }
}
