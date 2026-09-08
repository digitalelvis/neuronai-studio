<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Skills;

interface SkillRuntime
{
    /** @param  array<int, array<string, mixed>>  $bindings */
    public function resolveCatalog(array $bindings): SkillCatalog;

    /** @return array<int, object> Neuron ToolInterface instances */
    public function runtimeTools(SkillCatalog $catalog): array;

    public function augmentInstructions(string $instructions, SkillCatalog $catalog): string;
}
