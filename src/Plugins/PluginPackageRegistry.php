<?php

namespace DigitalElvis\NeuronAIStudio\Plugins;

use DigitalElvis\NeuronAIStudio\Models\PluginPackage;
use DigitalElvis\NeuronAIStudio\Models\PluginPackageSkill;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillArchiveImporter;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillParser;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PluginPackageRegistry
{
    public function __construct(
        protected SkillParser $parser,
        protected SkillArchiveImporter $skillImporter,
    ) {}

    /**
     * @param  array{source?: string, source_url?: ?string, source_path?: ?string}  $sourceMeta
     */
    public function ensureFromParsed(ParsedPlugin $parsed, array $sourceMeta = []): PluginPackage
    {
        $slug = $parsed->slug();

        if ($slug === '') {
            throw new InvalidArgumentException('Plugin manifest is missing a name/slug.');
        }

        $skills = $this->extractSkills($parsed);
        $mcpTemplates = $parsed->mcpServers;
        $contentHash = $this->hashPayload($skills, $mcpTemplates, $parsed->manifest);
        $version = $parsed->version() ?? '0.0.0';

        $existing = PluginPackage::query()
            ->where('slug', $slug)
            ->where('version', $version)
            ->first();

        if ($existing !== null) {
            if ($existing->content_hash !== $contentHash) {
                throw new InvalidArgumentException(
                    "Plugin package [{$slug}@{$version}] already exists with a different content hash. Bump the pack version before installing."
                );
            }

            return $existing->loadMissing('skills');
        }

        $package = PluginPackage::create([
            'slug' => $slug,
            'version' => $version,
            'content_hash' => $contentHash,
            'name' => (string) ($parsed->manifest['name'] ?? Str::headline($slug)),
            'description' => $parsed->description(),
            'manifest' => $parsed->manifest,
            'mcp_templates' => $mcpTemplates === [] ? null : $mcpTemplates,
            'source' => (string) ($sourceMeta['source'] ?? 'catalog'),
            'source_url' => $sourceMeta['source_url'] ?? null,
            'source_path' => $sourceMeta['source_path'] ?? $parsed->root,
        ]);

        foreach ($skills as $skill) {
            PluginPackageSkill::create([
                'plugin_package_id' => $package->id,
                'slug' => $skill['slug'],
                'description' => $skill['description'],
                'body' => $skill['body'],
                'resources' => $skill['resources'],
                'metadata' => $skill['metadata'],
            ]);
        }

        return $package->load('skills');
    }

    /**
     * @return list<array{slug: string, description: string, body: string, resources: ?array, metadata: ?array}>
     */
    protected function extractSkills(ParsedPlugin $parsed): array
    {
        $skills = [];

        foreach ($parsed->skillRoots as $skillRoot) {
            $skillFile = rtrim($skillRoot, '/\\').DIRECTORY_SEPARATOR.'SKILL.md';

            if (! is_file($skillFile)) {
                throw new InvalidArgumentException('SKILL.md not found at '.$skillRoot);
            }

            $parsedSkill = $this->parser->parse(File::get($skillFile));
            $resources = $this->skillImporter->collectResourcesFromRoot($skillRoot);

            $skills[] = [
                'slug' => $parsedSkill['slug'],
                'description' => $parsedSkill['description'],
                'body' => $parsedSkill['body'],
                'resources' => $resources === [] ? null : $resources,
                'metadata' => $parsedSkill['metadata'] === [] ? null : $parsedSkill['metadata'],
            ];
        }

        usort($skills, fn (array $a, array $b) => strcmp($a['slug'], $b['slug']));

        return $skills;
    }

    /**
     * @param  list<array{slug: string, description: string, body: string, resources: ?array, metadata: ?array}>  $skills
     * @param  array<string, array<string, mixed>>  $mcpTemplates
     * @param  array<string, mixed>  $manifest
     */
    protected function hashPayload(array $skills, array $mcpTemplates, array $manifest): string
    {
        $canonical = json_encode([
            'manifest' => $manifest,
            'skills' => $skills,
            'mcp' => $mcpTemplates,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $canonical);
    }
}
