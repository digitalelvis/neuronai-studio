<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Skills;

use DigitalElvis\NeuronAIStudio\Models\PluginPackageSkill;
use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;
use Illuminate\Support\Str;

class SkillRepository
{
    public function findByRef(string $ref): ?SkillContent
    {
        if (str_starts_with($ref, 'skill:pkg:')) {
            return $this->findPackageSkill(Str::after($ref, 'skill:pkg:'));
        }

        if (str_starts_with($ref, 'skill:db:')) {
            return SkillDefinition::query()->find((int) Str::after($ref, 'skill:db:'));
        }

        if (str_starts_with($ref, 'skill:')) {
            $slug = Str::after($ref, 'skill:');

            return SkillDefinition::query()->where('slug', $slug)->first();
        }

        return null;
    }

    protected function findPackageSkill(string $remainder): ?PluginPackageSkill
    {
        $parts = explode(':', $remainder, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        return PluginPackageSkill::query()
            ->where('plugin_package_id', (int) $parts[0])
            ->where('slug', $parts[1])
            ->first();
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
