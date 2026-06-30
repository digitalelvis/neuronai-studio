<?php

namespace DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators;

class DelayNodeCodeGenerator implements NodeCodeGeneratorInterface
{
    public function supports(string $type): bool
    {
        return $type === 'delay';
    }

    public function generate(array $nodePlan, CodegenContext $context): array
    {
        $data = $nodePlan['data'];
        $seconds = min(5, max(0, (int) ($data['seconds'] ?? 1)));
        $return = $context->returnStatement($nodePlan['returnType']);

        $body = <<<PHP
        sleep({$seconds});

        {$return}
PHP;

        return ['body' => $body, 'imports' => []];
    }
}
