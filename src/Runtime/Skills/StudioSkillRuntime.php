<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Skills;

use DigitalElvis\NeuronAIStudio\Runtime\Skills\Tools\ActivateSkillTool;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\Tools\ReadSkillFileTool;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\Tools\ReadSkillResourceTool;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\Tools\RunSkillScriptTool;

class StudioSkillRuntime implements SkillRuntime
{
    public function __construct(
        protected SkillResolver $resolver,
        protected SkillCatalogInjector $injector,
    ) {}

    /** @param  array<int, array<string, mixed>>  $bindings */
    public function resolveCatalog(array $bindings): SkillCatalog
    {
        return $this->resolver->resolve($bindings);
    }

    public function runtimeTools(SkillCatalog $catalog): array
    {
        if ($catalog->isEmpty()) {
            return [];
        }

        $tools = [
            new ActivateSkillTool($catalog),
            new ReadSkillFileTool($catalog),
            new ReadSkillResourceTool($catalog),
        ];

        if ((bool) config('neuronai-studio.skills.execution.enabled', false)) {
            $tools[] = new RunSkillScriptTool($catalog);
        }

        return $tools;
    }

    public function augmentInstructions(string $instructions, SkillCatalog $catalog): string
    {
        return $this->injector->augment($instructions, $catalog);
    }
}
