<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Skills;

use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;
use Illuminate\Support\Str;

class SkillRepository
{
    public function findByRef(string $ref): ?SkillDefinition
    {
        if (str_starts_with($ref, 'skill:db:')) {
            return SkillDefinition::query()->find((int) Str::after($ref, 'skill:db:'));
        }

        if (str_starts_with($ref, 'skill:')) {
            $slug = Str::after($ref, 'skill:');

            return SkillDefinition::query()->where('slug', $slug)->first();
        }

        return null;
    }

    /** @return list<SkillDefinition> */
    public function allForCatalog(): array
    {
        return SkillDefinition::query()
            ->orderBy('slug')
            ->get()
            ->all();
    }
}
