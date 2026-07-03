<?php

namespace DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators;

class ForkNodeCodeGenerator implements NodeCodeGeneratorInterface
{
    public function supports(string $type): bool
    {
        return $type === 'fork';
    }

    public function generate(array $nodePlan, CodegenContext $context): array
    {
        $parallel = $nodePlan['parallel'] ?? null;

        if (! is_array($parallel)) {
            return [
                'body' => 'return new StopEvent($state->all());',
                'imports' => [],
            ];
        }

        $eventClass = (string) $parallel['eventClass'];
        $branchLines = [];

        foreach ($parallel['branches'] as $branchId => $branch) {
            $key = var_export((string) $branchId, true);
            $branchLines[] = "            {$key} => new {$branch['eventClass']}(),";
        }

        $branches = implode("\n", $branchLines);

        $body = <<<PHP
        return new {$eventClass}([
{$branches}
        ]);
PHP;

        return [
            'body' => $body,
            'imports' => [],
        ];
    }
}
