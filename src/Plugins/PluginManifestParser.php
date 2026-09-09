<?php

namespace DigitalElvis\NeuronAIStudio\Plugins;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class PluginManifestParser
{
    /**
     * @return array<string, mixed>
     */
    public function readJsonFile(string $path): array
    {
        if (! is_readable($path)) {
            throw new InvalidArgumentException("JSON file not readable: {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException("Invalid JSON: {$path}");
        }

        return $decoded;
    }

    public function parseRoot(string $root): ParsedPlugin
    {
        $root = rtrim($root, '/\\');

        if (! is_dir($root)) {
            throw new InvalidArgumentException('Plugin root directory does not exist.');
        }

        $manifestPath = $this->resolveManifestPath($root);
        $manifest = $this->readJsonFile($manifestPath);

        $name = (string) ($manifest['name'] ?? '');

        if ($name === '') {
            throw new InvalidArgumentException('Plugin manifest must define name.');
        }

        $skillRoots = $this->discoverSkillRoots($root, $manifest);
        $mcpServers = $this->discoverMcpServers($root, $manifest);
        $requiredEnvKeys = $this->extractRequiredEnvKeys($mcpServers);

        return new ParsedPlugin($root, $manifest, $skillRoots, $mcpServers, $requiredEnvKeys);
    }

    protected function resolveManifestPath(string $root): string
    {
        $candidates = [
            $root.'/.claude-plugin/plugin.json',
            $root.'/plugin.json',
        ];

        foreach ($candidates as $candidate) {
            if (is_readable($candidate)) {
                return $candidate;
            }
        }

        throw new InvalidArgumentException('Plugin manifest (.claude-plugin/plugin.json) not found.');
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<int, string>
     */
    protected function discoverSkillRoots(string $root, array $manifest): array
    {
        $roots = [];

        $custom = $manifest['skills'] ?? null;

        if (is_string($custom) && $custom !== '') {
            $roots[] = $this->resolveRelativePath($root, $custom);
        } elseif (is_array($custom)) {
            foreach ($custom as $path) {
                if (is_string($path) && $path !== '') {
                    $roots[] = $this->resolveRelativePath($root, $path);
                }
            }
        }

        $defaultSkillsDir = $root.'/skills';

        if (is_dir($defaultSkillsDir)) {
            foreach (File::directories($defaultSkillsDir) as $dir) {
                if (is_file($dir.'/SKILL.md')) {
                    $roots[] = $dir;
                }
            }
        }

        if (is_file($root.'/SKILL.md')) {
            $roots[] = $root;
        }

        return array_values(array_unique(array_filter($roots, fn (string $path) => is_file($path.'/SKILL.md'))));
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, array<string, mixed>>
     */
    protected function discoverMcpServers(string $root, array $manifest): array
    {
        $servers = [];

        $defaultMcp = $root.'/.mcp.json';

        if (is_readable($defaultMcp)) {
            $fileServers = $this->readJsonFile($defaultMcp)['mcpServers'] ?? [];
            if (is_array($fileServers)) {
                $servers = array_merge($servers, $fileServers);
            }
        }

        $manifestMcp = $manifest['mcpServers'] ?? null;

        if (is_string($manifestMcp) && $manifestMcp !== '') {
            $path = $this->resolveRelativePath($root, $manifestMcp);
            if (is_readable($path)) {
                $fileServers = $this->readJsonFile($path)['mcpServers'] ?? [];
                if (is_array($fileServers)) {
                    $servers = array_merge($servers, $fileServers);
                }
            }
        } elseif (is_array($manifestMcp)) {
            $servers = array_merge($servers, $manifestMcp);
        }

        $normalized = [];

        foreach ($servers as $key => $config) {
            if (! is_string($key) || $key === '' || ! is_array($config)) {
                continue;
            }

            $normalized[$key] = $this->normalizeMcpConfig($root, $config);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function normalizeMcpConfig(string $root, array $config): array
    {
        $transport = 'stdio';

        if (isset($config['url']) && is_string($config['url']) && $config['url'] !== '') {
            $transport = 'http';
        } elseif (isset($config['transport']) && is_string($config['transport'])) {
            $transport = $config['transport'];
        }

        $env = is_array($config['env'] ?? null) ? $config['env'] : [];
        $args = is_array($config['args'] ?? null) ? $config['args'] : [];

        return [
            'transport' => $transport,
            'command' => isset($config['command']) ? (string) $config['command'] : null,
            'args' => array_map(function ($arg) use ($root) {
                return is_string($arg) ? $this->expandPluginRoot($root, $arg) : $arg;
            }, $args),
            'url' => isset($config['url']) ? (string) $config['url'] : null,
            'headers' => is_array($config['headers'] ?? null) ? $config['headers'] : [],
            'env' => array_map(function ($value) use ($root) {
                return is_string($value) ? $this->expandPluginRoot($root, $value) : $value;
            }, $env),
            'token_env' => isset($config['token_env']) ? (string) $config['token_env'] : null,
            'timeout' => isset($config['timeout']) ? (int) $config['timeout'] : 30,
        ];
    }

    protected function expandPluginRoot(string $root, string $value): string
    {
        return str_replace('${CLAUDE_PLUGIN_ROOT}', $root, $value);
    }

    protected function resolveRelativePath(string $root, string $path): string
    {
        if (str_starts_with($path, './')) {
            return rtrim($root, '/\\').'/'.ltrim($path, './');
        }

        if (! str_starts_with($path, '/')) {
            return rtrim($root, '/\\').'/'.$path;
        }

        return $path;
    }

    /**
     * @param  array<string, array<string, mixed>>  $mcpServers
     * @return array<int, string>
     */
    protected function extractRequiredEnvKeys(array $mcpServers): array
    {
        $keys = [];

        foreach ($mcpServers as $config) {
            $env = is_array($config['env'] ?? null) ? $config['env'] : [];

            foreach ($env as $key => $value) {
                if (! is_string($key) || $key === '') {
                    continue;
                }

                $keys[] = $key;
            }

            if (isset($config['token_env']) && is_string($config['token_env']) && $config['token_env'] !== '') {
                $keys[] = $config['token_env'];
            }
        }

        return array_values(array_unique($keys));
    }
}
