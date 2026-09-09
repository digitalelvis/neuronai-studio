<?php

namespace DigitalElvis\NeuronAIStudio\Plugins;

use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

class PluginPolicy
{
    public function enabled(): bool
    {
        return (bool) config('neuronai-studio.plugins.enabled', false);
    }

    public function assertEnabled(): void
    {
        if (! $this->enabled()) {
            throw new AuthorizationException('Plugins are disabled on this host.');
        }
    }

    public function mode(): string
    {
        return (string) config('neuronai-studio.plugins.mode', 'closed');
    }

    public function stdioAllowed(): bool
    {
        return (bool) config('neuronai-studio.plugins.stdio', false);
    }

    /**
     * @param  array{slug?: string, source?: string, source_url?: ?string, source_path?: ?string}  $context
     */
    public function assertSourceAllowed(array $context): void
    {
        $this->assertEnabled();

        $slug = (string) ($context['slug'] ?? '');
        $source = (string) ($context['source'] ?? 'catalog');
        $sourceUrl = isset($context['source_url']) ? (string) $context['source_url'] : null;
        $sourcePath = isset($context['source_path']) ? (string) $context['source_path'] : null;

        if ($this->mode() === 'closed') {
            if ($source === 'catalog' && $this->isCatalogSlug($slug)) {
                return;
            }

            if ($sourcePath !== null && $sourcePath !== '' && $this->isAllowedPath($sourcePath)) {
                return;
            }

            throw new AuthorizationException("Plugin [{$slug}] is not in the closed catalog.");
        }

        if ($this->mode() === 'allowlist') {
            if ($this->isCatalogSlug($slug) || $this->isAllowlisted($slug, $sourceUrl, $sourcePath)) {
                return;
            }

            throw new AuthorizationException("Plugin [{$slug}] is not allowlisted on this host.");
        }

        throw new AuthorizationException('Unsupported plugins.mode configuration.');
    }

    public function isCatalogSlug(string $slug): bool
    {
        if ($slug === '') {
            return false;
        }

        return app(\DigitalElvis\NeuronAIStudio\Registry\PluginCatalogRegistry::class)->has($slug);
    }

    protected function isAllowedPath(string $path): bool
    {
        $real = realpath($path);

        if ($real === false) {
            return false;
        }

        foreach ($this->catalogPaths() as $allowedRoot) {
            $allowedReal = realpath($allowedRoot);

            if ($allowedReal === false) {
                continue;
            }

            if ($real === $allowedReal || str_starts_with($real, $allowedReal.DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    protected function isAllowlisted(string $slug, ?string $sourceUrl, ?string $sourcePath): bool
    {
        $allowlist = config('neuronai-studio.plugins.allowlist', []);

        if (! is_array($allowlist)) {
            return false;
        }

        foreach ($allowlist as $entry) {
            $entry = (string) $entry;

            if ($entry === '') {
                continue;
            }

            if ($slug === $entry) {
                return true;
            }

            if ($sourceUrl !== null && $sourceUrl !== '' && str_contains($sourceUrl, $entry)) {
                return true;
            }

            if ($sourcePath !== null && $sourcePath !== '' && str_contains($sourcePath, $entry)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, string> */
    public function catalogPaths(): array
    {
        $paths = [
            dirname(__DIR__, 2).'/resources/plugins',
        ];

        $extra = config('neuronai-studio.plugins.catalog_paths', []);

        if (is_array($extra)) {
            foreach ($extra as $path) {
                if (is_string($path) && $path !== '') {
                    $paths[] = $path;
                }
            }
        }

        return $paths;
    }

    /** @return array<int, string> */
    public function allowedGitHubHosts(): array
    {
        $hosts = config('neuronai-studio.plugins.github_hosts', ['github.com']);

        return is_array($hosts) ? array_values(array_map('strval', $hosts)) : ['github.com'];
    }

    public function assertGitHubHostAllowed(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || ! in_array(strtolower($host), $this->allowedGitHubHosts(), true)) {
            throw new InvalidArgumentException('GitHub host is not allowed for plugin install.');
        }
    }
}
