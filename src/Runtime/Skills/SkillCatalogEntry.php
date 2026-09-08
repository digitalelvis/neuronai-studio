<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Skills;

use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;

class SkillCatalogEntry
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly SkillDefinition $definition,
    ) {}
}
