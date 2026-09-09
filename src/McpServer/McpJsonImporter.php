<?php

namespace DigitalElvis\NeuronAIStudio\McpServer;

use DigitalElvis\NeuronAIStudio\Models\McpServer;
use DigitalElvis\NeuronAIStudio\Plugins\PluginManifestParser;
use DigitalElvis\NeuronAIStudio\Registry\McpRegistry;
use Illuminate\Support\Str;
use InvalidArgumentException;

class McpJsonImporter
{
    public function __construct(
        protected PluginManifestParser $parser,
        protected McpRegistry $mcpRegistry,
    ) {}

    /**
     * @return array<int, McpServer>
     */
    public function import(string $json): array
    {
        $servers = $this->parseServers($json);
        $created = [];

        foreach ($servers as $key => $config) {
            if (! is_string($key) || $key === '' || ! is_array($config)) {
                continue;
            }

            $slug = Str::slug($key);
            $transport = (string) ($config['transport'] ?? 'stdio');

            if ($transport === 'stdio') {
                if (! empty($config['command'])) {
                    $this->mcpRegistry->assertStdioCommandAllowed($config['command']);
                }
            }

            $payload = [
                'name' => Str::headline($key),
                'slug' => $slug,
                'description' => null,
                'transport' => $transport,
                'command' => $config['command'] ?? null,
                'args' => is_array($config['args'] ?? null) ? $config['args'] : null,
                'url' => $config['url'] ?? null,
                'token_env' => $config['token_env'] ?? null,
                'headers' => is_array($config['headers'] ?? null) ? $config['headers'] : null,
                'env' => is_array($config['env'] ?? null) ? $config['env'] : null,
                'timeout' => (int) ($config['timeout'] ?? 30),
                'async' => $transport === 'sse',
                'enabled' => true,
            ];

            $existing = McpServer::query()->where('slug', $slug)->first();

            if ($existing !== null) {
                $existing->update($payload);
                $created[] = $existing->fresh();
            } else {
                $created[] = McpServer::create($payload);
            }
        }

        if ($created === []) {
            throw new InvalidArgumentException('No valid MCP servers found in JSON.');
        }

        return $created;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function parseServers(string $json): array
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Invalid JSON.');
        }

        $servers = $decoded['mcpServers'] ?? $decoded;

        if (! is_array($servers)) {
            throw new InvalidArgumentException('JSON must contain an mcpServers object.');
        }

        $normalized = [];

        foreach ($servers as $key => $config) {
            if (! is_string($key) || $key === '' || ! is_array($config)) {
                continue;
            }

            $transport = 'stdio';

            if (isset($config['url']) && is_string($config['url']) && $config['url'] !== '') {
                $transport = isset($config['transport']) && is_string($config['transport'])
                    ? $config['transport']
                    : 'http';
            } elseif (isset($config['transport']) && is_string($config['transport'])) {
                $transport = $config['transport'];
            }

            $normalized[$key] = [
                'transport' => $transport,
                'command' => isset($config['command']) ? (string) $config['command'] : null,
                'args' => is_array($config['args'] ?? null) ? $config['args'] : [],
                'url' => isset($config['url']) ? (string) $config['url'] : null,
                'headers' => is_array($config['headers'] ?? null) ? $config['headers'] : [],
                'env' => is_array($config['env'] ?? null) ? $config['env'] : [],
                'token_env' => isset($config['token_env']) ? (string) $config['token_env'] : null,
                'timeout' => isset($config['timeout']) ? (int) $config['timeout'] : 30,
            ];
        }

        return $normalized;
    }
}
