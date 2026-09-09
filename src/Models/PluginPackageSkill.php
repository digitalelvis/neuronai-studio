<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillContent;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillParser;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillPathPolicy;
use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PluginPackageSkill extends Model implements SkillContent
{
    protected $table;

    protected $fillable = [
        'plugin_package_id',
        'slug',
        'description',
        'body',
        'resources',
        'metadata',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('plugin_package_skills');

        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'resources' => 'array',
            'metadata' => 'array',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(PluginPackage::class, 'plugin_package_id');
    }

    public static function bindingRefFor(int|string $packageId, string $skillSlug): string
    {
        return 'skill:pkg:'.$packageId.':'.$skillSlug;
    }

    public function bindingRef(): string
    {
        return self::bindingRefFor((int) $this->plugin_package_id, $this->slug);
    }

    public function slug(): string
    {
        return (string) ($this->attributes['slug'] ?? '');
    }

    public function description(): string
    {
        return (string) ($this->attributes['description'] ?? '');
    }

    public function body(): string
    {
        return (string) ($this->attributes['body'] ?? '');
    }

    /** @return array<string, string> */
    public function files(): array
    {
        $resources = $this->resources ?? [];

        if (! is_array($resources)) {
            return [];
        }

        $files = [];

        foreach ($resources as $path => $contents) {
            if (! is_string($path) || ! is_string($contents)) {
                continue;
            }

            $files[$path] = $contents;
        }

        ksort($files);

        return $files;
    }

    public function skillMarkdown(): string
    {
        $parser = app(SkillParser::class);

        $frontmatter = $parser->buildFrontmatter(array_filter([
            'name' => $this->slug(),
            'description' => $this->description(),
            'metadata' => $this->metadata,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []));

        return trim($frontmatter."\n\n".$this->body());
    }

    public function isPathReadable(string $path): bool
    {
        return app(SkillPathPolicy::class)->isReadable($path) && array_key_exists($path, $this->files());
    }
}
