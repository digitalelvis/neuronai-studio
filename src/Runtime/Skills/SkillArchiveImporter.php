<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Skills;

use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use ZipArchive;

class SkillArchiveImporter
{
    public function __construct(
        protected SkillParser $parser,
        protected SkillPathPolicy $pathPolicy,
    ) {}

    /**
     * Import a skill from a zip/.skill path or an extracted directory.
     *
     * @param  array{overwrite?: bool, source?: string, source_url?: ?string, source_meta?: ?array, categories?: ?array, category?: ?string, display_name?: ?string, cover_image?: ?string}  $options
     */
    public function import(string $path, array $options = []): SkillDefinition
    {
        if (! file_exists($path)) {
            throw new InvalidArgumentException('Skill archive path does not exist.');
        }

        $tmpDir = null;

        try {
            if (is_dir($path)) {
                $root = $this->resolveSkillRoot($path);
            } else {
                $tmpDir = $this->extractArchive($path);
                $root = $this->resolveSkillRoot($tmpDir);
            }

            return $this->importFromRoot($root, $options);
        } finally {
            if ($tmpDir !== null && is_dir($tmpDir)) {
                File::deleteDirectory($tmpDir);
            }
        }
    }

    /**
     * @param  array{overwrite?: bool, source?: string, source_url?: ?string, source_meta?: ?array, categories?: ?array, category?: ?string, display_name?: ?string, cover_image?: ?string}  $options
     */
    public function importFromRoot(string $root, array $options = []): SkillDefinition
    {
        $skillFile = rtrim($root, '/\\').DIRECTORY_SEPARATOR.'SKILL.md';

        if (! is_file($skillFile)) {
            throw new InvalidArgumentException('SKILL.md not found at the skill root.');
        }

        $parsed = $this->parser->parse(File::get($skillFile));
        $resources = $this->collectResources($root);

        $categories = $options['categories']
            ?? $this->normalizeCategories($options['category'] ?? $parsed['category'] ?? null);

        $payload = [
            'slug' => $parsed['slug'],
            'display_name' => $options['display_name'] ?? $parsed['slug'],
            'description' => $parsed['description'],
            'license' => $parsed['license'],
            'compatibility' => $parsed['compatibility'],
            'metadata' => $parsed['metadata'] === [] ? null : $parsed['metadata'],
            'body' => $parsed['body'],
            'resources' => $resources === [] ? null : $resources,
            'categories' => $categories === [] ? null : $categories,
            'cover_image' => $options['cover_image'] ?? $this->detectCover($resources),
            'source' => $options['source'] ?? SkillDefinition::SOURCE_UPLOAD,
            'source_url' => $options['source_url'] ?? null,
            'source_meta' => $options['source_meta'] ?? null,
        ];

        $existing = null;

        if (isset($options['source_meta']['plugin_install_id'])) {
            $existing = SkillDefinition::query()
                ->where('source_meta->plugin_install_id', $options['source_meta']['plugin_install_id'])
                ->first();
        }

        if ($existing === null) {
            $existing = SkillDefinition::query()->where('slug', $parsed['slug'])->first();
        }

        if ($existing !== null) {
            if (! ($options['overwrite'] ?? false)) {
                throw new InvalidArgumentException("Skill [{$parsed['slug']}] already exists. Enable overwrite to replace it.");
            }

            $existing->update($payload);

            return $existing->fresh();
        }

        return SkillDefinition::create($payload);
    }

    protected function extractArchive(string $archivePath): string
    {
        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            throw new InvalidArgumentException('Unable to open skill archive. Expected a .zip or .skill file.');
        }

        $tmpDir = sys_get_temp_dir().'/neuronai-skill-'.uniqid('', true);
        File::ensureDirectoryExists($tmpDir);

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);

                if ($name === false) {
                    continue;
                }

                $normalized = str_replace('\\', '/', $name);

                if ($normalized === '' || str_ends_with($normalized, '/')) {
                    continue;
                }

                if (str_contains($normalized, '..') || str_starts_with($normalized, '/')) {
                    throw new InvalidArgumentException('Archive contains unsafe paths (zip-slip).');
                }

                $target = $tmpDir.'/'.$normalized;
                File::ensureDirectoryExists(dirname($target));
                $contents = $zip->getFromIndex($i);

                if ($contents === false) {
                    continue;
                }

                File::put($target, $contents);
            }
        } finally {
            $zip->close();
        }

        return $tmpDir;
    }

    protected function resolveSkillRoot(string $directory): string
    {
        $directory = rtrim($directory, '/\\');

        if (is_file($directory.DIRECTORY_SEPARATOR.'SKILL.md')) {
            return $directory;
        }

        $children = array_values(array_filter(scandir($directory) ?: [], fn ($name) => $name !== '.' && $name !== '..'));

        if (count($children) === 1) {
            $child = $directory.DIRECTORY_SEPARATOR.$children[0];
            if (is_dir($child) && is_file($child.DIRECTORY_SEPARATOR.'SKILL.md')) {
                return $child;
            }
        }

        foreach ($children as $childName) {
            $child = $directory.DIRECTORY_SEPARATOR.$childName;
            if (is_dir($child) && is_file($child.DIRECTORY_SEPARATOR.'SKILL.md')) {
                return $child;
            }
        }

        throw new InvalidArgumentException('Could not locate SKILL.md in the archive root.');
    }

    /** @return array<string, string> */
    protected function collectResources(string $root): array
    {
        $maxFiles = (int) config('neuronai-studio.skills.import.max_files', 200);
        $maxFileBytes = (int) config('neuronai-studio.skills.import.max_file_bytes', 512_000);
        $maxTotalBytes = (int) config('neuronai-studio.skills.import.max_total_bytes', 5_000_000);
        $binaryExtensions = config('neuronai-studio.skills.import.binary_extensions', []);

        $files = [];
        $total = 0;

        foreach (File::allFiles($root) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());

            if ($relative === 'SKILL.md' || str_starts_with($relative, '.')) {
                continue;
            }

            if (! $this->pathPolicy->isAllowed($relative)) {
                continue;
            }

            if (count($files) >= $maxFiles) {
                throw new InvalidArgumentException("Skill exceeds max file count ({$maxFiles}).");
            }

            $size = $file->getSize();
            if ($size > $maxFileBytes) {
                throw new InvalidArgumentException("File [{$relative}] exceeds max size.");
            }

            $total += $size;
            if ($total > $maxTotalBytes) {
                throw new InvalidArgumentException('Skill archive exceeds max total size.');
            }

            $extension = strtolower($file->getExtension());
            $raw = File::get($file->getPathname());

            if (in_array($extension, $binaryExtensions, true) || ! mb_check_encoding($raw, 'UTF-8')) {
                $files[$relative] = 'base64:'.base64_encode($raw);
            } else {
                $files[$relative] = $raw;
            }
        }

        ksort($files);

        return $files;
    }

    /** @return list<string> */
    protected function normalizeCategories(mixed $category): array
    {
        if ($category === null || $category === '') {
            return [];
        }

        if (is_array($category)) {
            $raw = $category;
        } else {
            $raw = preg_split('/[,|]+/', (string) $category) ?: [];
        }

        $allowed = array_keys(config('neuronai-studio.skills.categories', []));
        $normalized = [];

        foreach ($raw as $item) {
            if (! is_string($item) && ! is_numeric($item)) {
                continue;
            }

            $key = strtolower(str_replace([' ', '_'], '-', trim((string) $item)));
            if ($key !== '' && in_array($key, $allowed, true)) {
                $normalized[] = $key;
            }
        }

        return array_values(array_unique($normalized));
    }

    /** @deprecated */
    protected function normalizeCategory(?string $category): ?string
    {
        $list = $this->normalizeCategories($category);

        return $list[0] ?? null;
    }

    /** @param  array<string, string>  $resources */
    protected function detectCover(array $resources): ?string
    {
        foreach (['assets/cover.png', 'assets/cover.jpg', 'assets/cover.webp', 'assets/icon.png'] as $candidate) {
            if (isset($resources[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }
}
