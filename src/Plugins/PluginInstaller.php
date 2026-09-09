<?php

namespace DigitalElvis\NeuronAIStudio\Plugins;

use DigitalElvis\NeuronAIStudio\Models\PluginAccount;
use DigitalElvis\NeuronAIStudio\Models\PluginInstall;
use DigitalElvis\NeuronAIStudio\Registry\PluginCatalogRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillGitHubImporter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ZipArchive;

class PluginInstaller
{
    public function __construct(
        protected PluginPolicy $policy,
        protected PluginCatalogRegistry $catalog,
        protected PluginManifestParser $parser,
        protected PluginMaterializer $materializer,
        protected PluginAccountService $accounts,
    ) {}

    public function installFromCatalogSlug(string $slug): PluginInstall
    {
        $listing = $this->catalog->find($slug);

        if ($listing === null) {
            throw new InvalidArgumentException("Plugin [{$slug}] is not in the catalog.");
        }

        return $this->installFromPath(
            (string) $listing['path'],
            [
                'slug' => $slug,
                'source' => 'catalog',
                'source_path' => (string) $listing['path'],
            ],
        );
    }

    /**
     * @param  array{slug?: string, source?: string, source_url?: ?string, source_path?: ?string, overwrite?: bool}  $context
     */
    public function installFromPath(string $path, array $context = []): PluginInstall
    {
        $real = realpath($path);

        if ($real === false || ! is_dir($real)) {
            throw new InvalidArgumentException('Plugin path does not exist.');
        }

        $parsed = $this->parser->parseRoot($real);
        $slug = (string) ($context['slug'] ?? $parsed->slug());

        $this->policy->assertSourceAllowed([
            'slug' => $slug,
            'source' => (string) ($context['source'] ?? 'catalog'),
            'source_url' => $context['source_url'] ?? null,
            'source_path' => $real,
        ]);

        $existing = PluginInstall::query()->where('slug', $slug)->first();

        if ($existing !== null && ! ($context['overwrite'] ?? false)) {
            if ($existing->isInstalled()) {
                return $existing;
            }
        }

        if ($existing !== null) {
            $this->materializer->dematerialize($existing);
            $existing->accounts()->delete();
        }

        $install = PluginInstall::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => (string) ($parsed->manifest['name'] ?? Str::headline($slug)),
                'version' => $parsed->version(),
                'description' => $parsed->description(),
                'manifest' => $parsed->manifest,
                'source' => (string) ($context['source'] ?? 'catalog'),
                'source_url' => $context['source_url'] ?? null,
                'source_path' => $real,
                'status' => PluginInstall::STATUS_INSTALLED,
            ],
        );

        $materialized = $this->materializer->materialize($install, $parsed);

        $install->update(['materialized' => $materialized]);

        $this->accounts->createDefaultAccount($install, $parsed->requiredEnvKeys, $parsed->mcpServers);

        return $install->fresh(['accounts']);
    }

    public function installFromArchive(string $archivePath, array $context = []): PluginInstall
    {
        $tmpDir = sys_get_temp_dir().'/neuronai-plugin-'.uniqid('', true);

        try {
            $root = $this->extractArchive($archivePath, $tmpDir);
            $parsed = $this->parser->parseRoot($root);

            return $this->installFromPath($root, array_merge($context, [
                'slug' => $context['slug'] ?? $parsed->slug(),
                'source' => $context['source'] ?? 'upload',
            ]));
        } finally {
            if (is_dir($tmpDir)) {
                File::deleteDirectory($tmpDir);
            }
        }
    }

    public function installFromGitHub(string $url, array $context = []): PluginInstall
    {
        $this->policy->assertGitHubHostAllowed($url);

        $parsedUrl = app(SkillGitHubImporter::class)->parseGitHubUrl($url);
        $tmpDir = sys_get_temp_dir().'/neuronai-plugin-gh-'.uniqid('', true);
        File::ensureDirectoryExists($tmpDir);

        try {
            $this->downloadGitHubTree($parsedUrl, $tmpDir);
            $parsed = $this->parser->parseRoot($tmpDir);

            return $this->installFromPath($tmpDir, array_merge($context, [
                'slug' => $context['slug'] ?? $parsed->slug(),
                'source' => 'github',
                'source_url' => $url,
            ]));
        } catch (\Throwable $e) {
            if (is_dir($tmpDir)) {
                File::deleteDirectory($tmpDir);
            }

            throw $e;
        }
    }

    public function uninstall(PluginInstall $install): void
    {
        $install->agentBindings()->delete();
        $this->materializer->dematerialize($install);
        $install->accounts()->delete();
        $install->update(['status' => PluginInstall::STATUS_UNINSTALLED]);
    }

    protected function extractArchive(string $archivePath, string $tmpDir): string
    {
        File::ensureDirectoryExists($tmpDir);

        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            throw new InvalidArgumentException('Unable to open plugin archive.');
        }

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
                    throw new InvalidArgumentException('Archive contains unsafe paths.');
                }

                $dest = $tmpDir.'/'.$normalized;
                File::ensureDirectoryExists(dirname($dest));
                $contents = $zip->getFromIndex($i);

                if ($contents !== false) {
                    File::put($dest, $contents);
                }
            }
        } finally {
            $zip->close();
        }

        return $this->resolvePluginRoot($tmpDir);
    }

    protected function resolvePluginRoot(string $dir): string
    {
        if (is_readable($dir.'/.claude-plugin/plugin.json') || is_readable($dir.'/plugin.json')) {
            return $dir;
        }

        foreach (File::directories($dir) as $sub) {
            if (is_readable($sub.'/.claude-plugin/plugin.json') || is_readable($sub.'/plugin.json')) {
                return $sub;
            }
        }

        throw new InvalidArgumentException('Plugin manifest not found in archive.');
    }

    /**
     * @param  array{owner: string, repo: string, ref: string, path: string}  $parsed
     */
    protected function downloadGitHubTree(array $parsed, string $targetDir): void
    {
        $zipUrl = sprintf(
            'https://codeload.github.com/%s/%s/zip/refs/heads/%s',
            $parsed['owner'],
            $parsed['repo'],
            $parsed['ref']
        );

        $response = \Illuminate\Support\Facades\Http::timeout(60)->get($zipUrl);

        if (! $response->successful()) {
            $zipUrl = sprintf(
                'https://api.github.com/repos/%s/%s/zipball/%s',
                $parsed['owner'],
                $parsed['repo'],
                $parsed['ref']
            );
            $response = \Illuminate\Support\Facades\Http::timeout(60)->get($zipUrl);
        }

        if (! $response->successful()) {
            throw new InvalidArgumentException('Failed to download plugin from GitHub.');
        }

        $zipPath = $targetDir.'.zip';
        File::put($zipPath, $response->body());

        $extractDir = $targetDir.'_extract';
        File::ensureDirectoryExists($extractDir);

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new InvalidArgumentException('Unable to open GitHub archive.');
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);

                if ($name === false) {
                    continue;
                }

                $normalized = str_replace('\\', '/', $name);

                if (str_contains($normalized, '..') || str_ends_with($normalized, '/')) {
                    continue;
                }

                $parts = explode('/', $normalized);
                array_shift($parts);
                $relative = implode('/', $parts);

                if ($relative === '') {
                    continue;
                }

                if ($parsed['path'] !== '') {
                    if ($relative !== $parsed['path'] && ! str_starts_with($relative, $parsed['path'].'/')) {
                        continue;
                    }

                    $relative = ltrim(substr($relative, strlen($parsed['path'])), '/');
                }

                if ($relative === '') {
                    continue;
                }

                $dest = $targetDir.'/'.$relative;
                File::ensureDirectoryExists(dirname($dest));
                $contents = $zip->getFromIndex($i);

                if ($contents !== false) {
                    File::put($dest, $contents);
                }
            }
        } finally {
            $zip->close();
            File::delete($zipPath);

            if (is_dir($extractDir)) {
                File::deleteDirectory($extractDir);
            }
        }
    }
}
