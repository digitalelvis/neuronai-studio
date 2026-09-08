<?php

namespace DigitalElvis\NeuronAIStudio\Codegen;

use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillCatalog;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillCatalogEntry;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillParser;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillPathPolicy;
use Illuminate\Support\Facades\File;

class ExportedSkillLoader
{
    /**
     * Build a skill catalog from exported SKILL.md snapshots on disk.
     *
     * @param  list<string>  $slugs
     */
    public static function fromDirectory(string $directory, array $slugs): SkillCatalog
    {
        $parser = new SkillParser;
        $entries = [];

        foreach ($slugs as $slug) {
            $slug = trim((string) $slug);

            if ($slug === '') {
                continue;
            }

            $skillDir = rtrim($directory, '/\\').'/'.$slug;
            $skillFile = $skillDir.'/SKILL.md';

            if (! File::exists($skillFile)) {
                continue;
            }

            $parsed = $parser->parse(File::get($skillFile));
            $definition = new SkillDefinition([
                'slug' => $parsed['slug'],
                'description' => $parsed['description'],
                'license' => $parsed['license'],
                'compatibility' => $parsed['compatibility'],
                'metadata' => $parsed['metadata'],
                'body' => $parsed['body'],
                'category' => $parsed['category'] ?? null,
                'resources' => self::loadSkillFiles($skillDir),
            ]);

            $entries[] = new SkillCatalogEntry(
                name: $definition->slug,
                description: $definition->description,
                definition: $definition,
            );
        }

        return new SkillCatalog($entries);
    }

    /** @return array<string, string> */
    protected static function loadSkillFiles(string $skillDir): array
    {
        $policy = new SkillPathPolicy;
        $files = [];

        if (! is_dir($skillDir)) {
            return [];
        }

        foreach (File::allFiles($skillDir) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());

            if ($relative === 'SKILL.md' || ! $policy->isAllowed($relative)) {
                continue;
            }

            $files[$relative] = File::get($file->getPathname());
        }

        ksort($files);

        return $files;
    }
}
