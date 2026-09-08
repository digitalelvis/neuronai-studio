<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillPathPolicy;
use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use DigitalElvis\NeuronAIStudio\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SkillDefinition extends Model
{
    use BelongsToTenant;

    public const SOURCE_STUDIO = 'studio';

    public const SOURCE_UPLOAD = 'upload';

    public const SOURCE_GITHUB = 'github';

    protected $table;

    protected $fillable = [
        'slug',
        'display_name',
        'categories',
        'cover_image',
        'source',
        'source_url',
        'source_meta',
        'description',
        'license',
        'compatibility',
        'metadata',
        'body',
        'resources',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('skill_definitions');

        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'resources' => 'array',
            'source_meta' => 'array',
            'categories' => 'array',
        ];
    }

    public function bindingRef(): string
    {
        return "skill:db:{$this->id}";
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

    /** @deprecated Use files() */
    public function referenceFiles(): array
    {
        return $this->filesUnder('references/');
    }

    /** @return array<string, string> */
    public function filesUnder(string $prefix): array
    {
        $prefix = ltrim(str_replace('\\', '/', $prefix), '/');
        if ($prefix !== '' && ! str_ends_with($prefix, '/')) {
            $prefix .= '/';
        }

        $files = [];

        foreach ($this->files() as $path => $contents) {
            if ($prefix === '' || str_starts_with($path, $prefix)) {
                $files[$path] = $contents;
            }
        }

        return $files;
    }

    /** @return list<string> */
    public function scriptPaths(): array
    {
        return array_keys($this->filesUnder('scripts/'));
    }

    /**
     * Nested tree for the skill viewer sidebar.
     *
     * @return array<string, mixed>
     */
    public function fileTree(): array
    {
        $tree = [];

        foreach (array_keys($this->files()) as $path) {
            $parts = explode('/', $path);
            $cursor = &$tree;

            foreach ($parts as $index => $part) {
                $isLeaf = $index === count($parts) - 1;

                if ($isLeaf) {
                    $cursor[$part] = $path;
                } else {
                    if (! isset($cursor[$part]) || ! is_array($cursor[$part])) {
                        $cursor[$part] = [];
                    }
                    $cursor = &$cursor[$part];
                }
            }

            unset($cursor);
        }

        return $tree;
    }

    public function displayName(): string
    {
        $name = trim((string) ($this->display_name ?? ''));

        return $name !== '' ? $name : $this->slug;
    }

    public function coverUrl(): ?string
    {
        $cover = trim((string) ($this->cover_image ?? ''));

        if ($cover === '') {
            return null;
        }

        if (str_starts_with($cover, 'http://') || str_starts_with($cover, 'https://') || str_starts_with($cover, 'data:')) {
            return $cover;
        }

        if (Storage::disk(config('neuronai-studio.skills.cover_disk', 'public'))->exists($cover)) {
            return Storage::disk(config('neuronai-studio.skills.cover_disk', 'public'))->url($cover);
        }

        return $cover;
    }

    public function skillMarkdown(): string
    {
        $parser = app(\DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillParser::class);

        $frontmatter = $parser->buildFrontmatter(array_filter([
            'name' => $this->slug,
            'description' => $this->description,
            'license' => $this->license,
            'compatibility' => $this->compatibility,
            'metadata' => $this->metadata,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []));

        return trim($frontmatter."\n\n".(string) $this->body);
    }

    /** @return list<string> */
    public function categoryList(): array
    {
        $categories = $this->categories ?? [];

        if (! is_array($categories)) {
            return [];
        }

        $keys = array_values(array_filter(array_map(
            fn ($value) => is_string($value) ? trim($value) : '',
            $categories
        )));

        return array_values(array_unique($keys));
    }

    public function hasCategory(string $key): bool
    {
        return in_array($key, $this->categoryList(), true);
    }

    /** @return list<string> */
    public static function categoryKeys(): array
    {
        return array_keys(config('neuronai-studio.skills.categories', []));
    }

    public function isPathReadable(string $path): bool
    {
        return app(SkillPathPolicy::class)->isReadable($path) && array_key_exists($path, $this->files());
    }
}
