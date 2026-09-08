<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Skills;

class SkillCatalogInjector
{
    public function augment(string $instructions, SkillCatalog $catalog): string
    {
        if ($catalog->isEmpty()) {
            return $instructions;
        }

        $lines = [
            trim($instructions),
            '',
            '## Available skills',
            'The following skills are attached to this agent. Use activate_skill to load full instructions when a task matches. Use read_skill_file for references/, scripts/, or assets/. When skill script execution is enabled, use run_skill_script for scripts/.',
            '',
        ];

        foreach ($catalog->entries() as $entry) {
            $lines[] = "- **{$entry->name}**: {$entry->description}";
        }

        return trim(implode("\n", $lines));
    }
}
