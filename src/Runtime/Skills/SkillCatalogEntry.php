<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Skills;

class SkillCatalogEntry
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly SkillContent $definition,
    ) {}
}
