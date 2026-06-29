<?php

namespace ElvisLopesDigital\NeuronAIStudio\Codegen\NodeCodeGenerators;

interface NodeCodeGeneratorInterface
{
    public function supports(string $type): bool;

    /**
     * @param  array{id: string, type: string, data: array<string, mixed>, returnType: string, branchReturns: array<string, string>}  $nodePlan
     * @return array{body: string, imports: array<int, string>}
     */
    public function generate(array $nodePlan, CodegenContext $context): array;
}
