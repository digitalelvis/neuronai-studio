<?php

namespace DigitalElvis\NeuronAIStudio\Plugins;

class ParsedPlugin
{
    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<int, string>  $skillRoots
     * @param  array<string, array<string, mixed>>  $mcpServers
     * @param  array<int, string>  $requiredEnvKeys
     */
    public function __construct(
        public readonly string $root,
        public readonly array $manifest,
        public readonly array $skillRoots,
        public readonly array $mcpServers,
        public readonly array $requiredEnvKeys,
    ) {}

    public function slug(): string
    {
        return (string) ($this->manifest['name'] ?? '');
    }

    public function version(): ?string
    {
        $version = $this->manifest['version'] ?? null;

        return is_string($version) && $version !== '' ? $version : null;
    }

    public function description(): ?string
    {
        $description = $this->manifest['description'] ?? null;

        return is_string($description) && $description !== '' ? $description : null;
    }
}
