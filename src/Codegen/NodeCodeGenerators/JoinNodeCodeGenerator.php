<?php

namespace DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators;

class JoinNodeCodeGenerator implements NodeCodeGeneratorInterface
{
    public function supports(string $type): bool
    {
        return $type === 'join';
    }

    public function generate(array $nodePlan, CodegenContext $context): array
    {
        $data = $nodePlan['data'];
        $outputKey = var_export((string) ($data['output_key'] ?? 'parallel_results'), true);
        $return = $context->returnStatement($nodePlan['returnType']);

        $body = <<<PHP
        \$state->set({$outputKey}, \$event->getAllResults());

        {$return}
PHP;

        return [
            'body' => $body,
            'imports' => [],
        ];
    }
}
