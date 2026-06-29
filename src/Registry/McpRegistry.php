<?php

namespace DigitalElvis\NeuronAIStudio\Registry;

use DigitalElvis\NeuronAIStudio\MCP\McpStdioTransport;
use DigitalElvis\NeuronAIStudio\Models\McpServer;
use Illuminate\Support\Str;
use NeuronAI\MCP\McpClient;

class McpRegistry
{
    /** @return array<string, array<string, mixed>> */
    public function all(bool $includeDisabled = true): array
    {
        $merged = [];

        foreach (config('neuronai-studio.mcp_servers', []) as $slug => $server) {
            $merged[$slug] = $this->normalizeEntry($slug, $server, 'config');
        }

        if (! class_exists(McpServer::class)) {
            return $merged;
        }

        try {
            $query = McpServer::query()->orderBy('name');

            if (! $includeDisabled) {
                $query->where('enabled', true);
            }

            foreach ($query->get() as $server) {
                $merged[$server->slug] = $this->normalizeEntry(
                    $server->slug,
                    $server->toRegistryEntry(),
                    'database',
                    $server->id,
                );
            }
        } catch (\Throwable) {
            return $merged;
        }

        return $merged;
    }

    /** @return array<string, string> */
    public function labels(bool $includeDisabled = false): array
    {
        return collect($this->all($includeDisabled))
            ->filter(fn (array $entry) => $includeDisabled || ($entry['enabled'] ?? true))
            ->mapWithKeys(fn (array $entry, string $slug) => [$slug => $entry['label'] ?? Str::headline($slug)])
            ->all();
    }

    /** @return array<string, mixed>|null */
    public function find(string $slug): ?array
    {
        return $this->all()[$slug] ?? null;
    }

    /** @return array<string, mixed> */
    public function resolveConfig(string $slug): array
    {
        $entry = $this->find($slug);

        if ($entry === null) {
            throw new \InvalidArgumentException("MCP server [{$slug}] not found.");
        }

        if (($entry['enabled'] ?? true) === false) {
            throw new \InvalidArgumentException("MCP server [{$slug}] is disabled.");
        }

        return $this->buildConnectorConfig($entry);
    }

    /** @return array<string, mixed> */
    public function configFor(string $slug): array
    {
        $entry = $this->find($slug);

        if ($entry === null) {
            return [];
        }

        return [
            'connector' => $this->buildConnectorArray($entry, enforceAllowlist: false),
            'label' => $entry['label'] ?? Str::headline($slug),
            'description' => $entry['description'] ?? null,
        ];
    }

    public function resolveToken(?string $tokenEnv): ?string
    {
        if ($tokenEnv === null || $tokenEnv === '') {
            return null;
        }

        $value = env($tokenEnv);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return array{success: bool, tools?: array<int, string>, error?: string} */
    public function testConnection(string $slug): array
    {
        try {
            $config = $this->resolveConfig($slug);
            return $this->testConnectionWithConfig($config);
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /** @param  array<string, mixed>  $payload */
    public function testConnectionFromPayload(array $payload): array
    {
        try {
            $entry = $this->normalizeEntry($payload['slug'] ?? 'preview', $payload, 'database');
            $config = $this->buildConnectorConfig($entry);

            return $this->testConnectionWithConfig($config);
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /** @param  array<string, mixed>  $config */
    protected function testConnectionWithConfig(array $config): array
    {
        $client = new McpClient($config);
        $tools = $client->listTools();

        return [
            'success' => true,
            'tools' => array_values(array_map(fn (array $tool) => $tool['name'], $tools)),
        ];
    }

    public function assertStdioCommandAllowed(?string $command): void
    {
        if ($command === null || $command === '') {
            return;
        }

        $allowlist = config('neuronai-studio.mcp_stdio_allowlist', []);

        if ($allowlist === []) {
            return;
        }

        foreach ($allowlist as $allowed) {
            if ($command === $allowed || str_starts_with($command, rtrim($allowed, '*'))) {
                return;
            }
        }

        throw new \InvalidArgumentException("Stdio command [{$command}] is not in the MCP stdio allowlist.");
    }

    /** @param  array<string, mixed>  $server */
    protected function normalizeEntry(string $slug, array $server, string $source, ?int $id = null): array
    {
        $connector = $server['connector'] ?? [];

        return [
            'slug' => $slug,
            'id' => $id,
            'source' => $source,
            'label' => $server['label'] ?? $server['name'] ?? Str::headline($slug),
            'description' => $server['description'] ?? null,
            'transport' => $server['transport'] ?? $this->inferTransport($server, $connector),
            'command' => $server['command'] ?? $connector['command'] ?? null,
            'args' => $server['args'] ?? $connector['args'] ?? [],
            'url' => $server['url'] ?? $connector['url'] ?? null,
            'token_env' => $server['token_env'] ?? $connector['token_env'] ?? null,
            'headers' => $server['headers'] ?? $connector['headers'] ?? [],
            'env' => $server['env'] ?? $connector['env'] ?? [],
            'only_tools' => $server['only_tools'] ?? null,
            'exclude_tools' => $server['exclude_tools'] ?? [],
            'timeout' => (int) ($server['timeout'] ?? $connector['timeout'] ?? 30),
            'async' => (bool) ($server['async'] ?? $connector['async'] ?? false),
            'enabled' => (bool) ($server['enabled'] ?? true),
            'metadata' => $server['metadata'] ?? [],
            'connector' => $connector,
        ];
    }

    /** @param  array<string, mixed>  $server */
    /** @param  array<string, mixed>  $connector */
    protected function inferTransport(array $server, array $connector): string
    {
        if (isset($server['transport'])) {
            return $server['transport'];
        }

        if (isset($connector['command']) || isset($server['command'])) {
            return 'stdio';
        }

        if (($connector['async'] ?? $server['async'] ?? false) === true) {
            return 'sse';
        }

        if (isset($connector['url']) || isset($server['url'])) {
            return 'http';
        }

        return 'stdio';
    }

    /** @param  array<string, mixed>  $entry */
    protected function buildConnectorConfig(array $entry, bool $enforceAllowlist = true): array
    {
        $connector = $entry['connector'] ?? [];

        if (isset($connector['transport']) && $connector['transport'] instanceof \NeuronAI\MCP\McpTransportInterface) {
            return ['transport' => $connector['transport']];
        }

        $array = $this->buildConnectorArray($entry, $enforceAllowlist);

        if (($entry['transport'] ?? 'stdio') === 'stdio' && isset($array['command'])) {
            return [
                'transport' => new McpStdioTransport($array),
            ];
        }

        return $array;
    }

    /** @param  array<string, mixed>  $entry */
    protected function buildConnectorArray(array $entry, bool $enforceAllowlist = true): array
    {
        $connector = $entry['connector'] ?? [];

        if (isset($connector['transport']) && $connector['transport'] instanceof \NeuronAI\MCP\McpTransportInterface) {
            return ['transport' => $connector['transport']];
        }

        $transport = $entry['transport'] ?? 'stdio';
        $config = ['timeout' => (int) ($entry['timeout'] ?? 30)];

        if ($transport === 'stdio') {
            if ($enforceAllowlist) {
                $this->assertStdioCommandAllowed($entry['command'] ?? null);
            }

            $config['command'] = $entry['command'];
            $config['args'] = $this->resolveEnvValues($entry['args'] ?? []);

            if (! empty($entry['env'])) {
                $config['env'] = $this->resolveEnvValues($entry['env']);
            }

            return array_filter($config, fn ($value) => $value !== null);
        }

        $config['url'] = $entry['url'];
        $token = $this->resolveToken($entry['token_env'] ?? null);

        if ($token !== null) {
            $config['token'] = $token;
        }

        if (! empty($entry['headers'])) {
            $config['headers'] = $this->resolveEnvValues($entry['headers']);
        }

        if ($transport === 'sse') {
            $config['async'] = true;
        }

        return array_filter($config, fn ($value) => $value !== null && $value !== []);
    }

    /** @param  array<string|int, mixed>  $values */
    protected function resolveEnvValues(array $values): array
    {
        $resolved = [];

        foreach ($values as $key => $value) {
            $resolved[$key] = is_string($value) ? $this->resolveEnvValue($value) : $value;
        }

        return $resolved;
    }

    protected function resolveEnvValue(string $value): string
    {
        if (str_starts_with($value, 'env:')) {
            return (string) env(Str::after($value, 'env:'), '');
        }

        if (preg_match('/^\{\{\s*env\.([A-Z0-9_]+)\s*\}\}$/', $value, $matches)) {
            return (string) env($matches[1], '');
        }

        return $value;
    }
}
