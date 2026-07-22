<?php

namespace DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators;

class InvokeNodeCodeGenerator implements NodeCodeGeneratorInterface
{
    public function supports(string $type): bool
    {
        return $type === 'invoke';
    }

    public function generate(array $nodePlan, CodegenContext $context): array
    {
        $data = $nodePlan['data'];
        $outputKey = var_export((string) ($data['output_key'] ?? 'invoke_result'), true);
        $return = $context->returnStatement($nodePlan['returnType']);
        $hookClass = ltrim((string) ($data['hook_class'] ?? ''), '\\');
        $classLiteral = $hookClass !== '' ? '\\'.$hookClass : '';

        if ($classLiteral === '') {
            $body = <<<PHP
        throw new \\RuntimeException('Invoke node requires data.hook_class (FQCN).');

        {$return}
PHP;

            return ['body' => $body, 'imports' => []];
        }

        $body = <<<PHP
        // Keep config('neuronai-studio.invoke_hooks') allowlist aligned with this FQCN.
        \$state->set({$outputKey}, app({$classLiteral}::class)(\$state));

        {$return}
PHP;

        return ['body' => $body, 'imports' => []];
    }
}
