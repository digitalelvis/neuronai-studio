<?php

namespace DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators;

class StopNodeCodeGenerator implements NodeCodeGeneratorInterface
{
    public function supports(string $type): bool
    {
        return $type === 'stop';
    }

    public function generate(array $nodePlan, CodegenContext $context): array
    {
        return [
            'body' => 'return new StopEvent($state->all());',
            'imports' => [],
        ];
    }
}
