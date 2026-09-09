<?php

namespace DigitalElvis\NeuronAIStudio\Registry;

use Illuminate\Support\Str;
use InvalidArgumentException;

class PluginCatalogRegistry
{
    /** @var array<string, array<string, mixed>>|null */
    protected ?array $cache = null;

    /** @return array<int, array<string, mixed>> */
    public function listings(): array
    {
        return array_values($this->all());
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $merged = [];

        foreach ($this->pathsToScan() as $basePath) {
            $marketplaceFile = rtrim($basePath, '/\\').'/.claude-plugin/marketplace.json';

            if (! is_readable($marketplaceFile)) {
                $marketplaceFile = rtrim($basePath, '/\\').'/marketplace.json';
            }

            if (! is_readable($marketplaceFile)) {
                continue;
            }

            $json = json_decode((string) file_get_contents($marketplaceFile), true);

            if (! is_array($json)) {
                continue;
            }

            $plugins = $json['plugins'] ?? [];

            if (! is_array($plugins)) {
                continue;
            }

            foreach ($plugins as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $slug = (string) ($entry['name'] ?? '');

                if ($slug === '') {
                    continue;
                }

                $source = $entry['source'] ?? null;
                $pluginPath = $this->resolvePluginPath($basePath, $source, $slug);

                if ($pluginPath === null || ! is_dir($pluginPath)) {
                    continue;
                }

                $merged[$slug] = [
                    'slug' => $slug,
                    'title' => (string) ($entry['title'] ?? $entry['name'] ?? Str::headline($slug)),
                    'name' => (string) ($entry['name'] ?? Str::headline($slug)),
                    'description' => (string) ($entry['description'] ?? ''),
                    'version' => (string) ($entry['version'] ?? ''),
                    'categories' => is_array($entry['categories'] ?? null) ? $entry['categories'] : [],
                    'featured' => (bool) ($entry['featured'] ?? false),
                    'icon' => isset($entry['icon']) ? (string) $entry['icon'] : null,
                    'path' => $pluginPath,
                    'source' => 'catalog',
                ];
            }
        }

        foreach (config('neuronai-studio.plugins.catalog', []) as $slug => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $slug = (string) $slug;
            $path = (string) ($entry['path'] ?? '');

            if ($slug === '' || $path === '' || ! is_dir($path)) {
                continue;
            }

            $merged[$slug] = [
                'slug' => $slug,
                'title' => (string) ($entry['title'] ?? $entry['name'] ?? Str::headline($slug)),
                'name' => (string) ($entry['name'] ?? Str::headline($slug)),
                'description' => (string) ($entry['description'] ?? ''),
                'version' => (string) ($entry['version'] ?? ''),
                'categories' => is_array($entry['categories'] ?? null) ? $entry['categories'] : [],
                'featured' => (bool) ($entry['featured'] ?? false),
                'icon' => isset($entry['icon']) ? (string) $entry['icon'] : null,
                'path' => realpath($path) ?: $path,
                'source' => 'config',
            ];
        }

        return $this->cache = $merged;
    }

    public function has(string $slug): bool
    {
        return isset($this->all()[$slug]);
    }

    /** @return array<string, mixed>|null */
    public function find(string $slug): ?array
    {
        return $this->all()[$slug] ?? null;
    }

    /** @return array<int, string> */
    protected function pathsToScan(): array
    {
        return app(\DigitalElvis\NeuronAIStudio\Plugins\PluginPolicy::class)->catalogPaths();
    }

    protected function resolvePluginPath(string $basePath, mixed $source, string $slug): ?string
    {
        if (is_string($source) && $source !== '') {
            $path = str_starts_with($source, './')
                ? rtrim($basePath, '/\\').'/'.ltrim($source, './')
                : $source;

            return realpath($path) ?: $path;
        }

        $candidates = [
            rtrim($basePath, '/\\').'/plugins/'.$slug,
            rtrim($basePath, '/\\').'/'.$slug,
        ];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return realpath($candidate) ?: $candidate;
            }
        }

        return null;
    }

    public function flushCache(): void
    {
        $this->cache = null;
    }
}
