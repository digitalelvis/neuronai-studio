<?php

namespace DigitalElvis\NeuronAIStudio\Plugins;

use DigitalElvis\NeuronAIStudio\Models\McpServer;
use DigitalElvis\NeuronAIStudio\Models\PluginInstall;
use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;
use DigitalElvis\NeuronAIStudio\Registry\McpRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillArchiveImporter;
use Illuminate\Support\Str;

class PluginMaterializer
{
    public function __construct(
        protected SkillArchiveImporter $skillImporter,
        protected PluginManifestParser $parser,
        protected McpRegistry $mcpRegistry,
        protected PluginPolicy $policy,
    ) {}

    /**
     * @return array{skill_ids: array<int, int>, mcp_slugs: array<int, string>}
     */
    public function materialize(PluginInstall $install, ParsedPlugin $parsed): array
    {
        $skillIds = [];
        $mcpSlugs = [];

        foreach ($parsed->skillRoots as $skillRoot) {
            $skill = $this->skillImporter->importFromRoot($skillRoot, [
                'overwrite' => true,
                'source' => SkillDefinition::SOURCE_PLUGIN,
                'source_url' => $install->source_url,
                'source_meta' => [
                    'plugin_install_id' => $install->id,
                    'plugin_slug' => $install->slug,
                ],
            ]);

            $skillIds[] = $skill->id;
        }

        foreach ($parsed->mcpServers as $connectorKey => $config) {
            $transport = (string) ($config['transport'] ?? 'stdio');

            if ($transport === 'stdio') {
                if (! $this->policy->stdioAllowed()) {
                    throw new \Illuminate\Auth\Access\AuthorizationException(
                        "Plugin MCP connector [{$connectorKey}] uses stdio transport, which is disabled on this host."
                    );
                }

                $this->mcpRegistry->assertStdioCommandAllowed($config['command'] ?? null);
            }

            $slug = $this->mcpSlugFor($install, $connectorKey);

            $env = is_array($config['env'] ?? null) ? $config['env'] : [];
            $credentialHints = [];

            foreach ($env as $envKey => $envValue) {
                if (! is_string($envKey) || $envKey === '') {
                    continue;
                }

                $varName = $this->suggestVariableName($install, $envKey);
                $env[$envKey] = 'var:'.$varName;
                $credentialHints[$envKey] = 'var:'.$varName;
            }

            $tokenEnv = null;

            if (isset($config['token_env']) && is_string($config['token_env']) && $config['token_env'] !== '') {
                $tokenKey = $config['token_env'];
                $varName = $this->suggestVariableName($install, $tokenKey);
                $tokenEnv = 'var:'.$varName;
                $credentialHints[$tokenKey] = 'var:'.$varName;
            }

            $server = McpServer::query()->where('slug', $slug)->first();

            $payload = [
                'name' => Str::headline($connectorKey).' ('.$install->name.')',
                'slug' => $slug,
                'description' => 'Connector from plugin '.$install->slug,
                'transport' => $transport,
                'command' => $config['command'] ?? null,
                'args' => $config['args'] ?? [],
                'url' => $config['url'] ?? null,
                'token_env' => $tokenEnv,
                'headers' => $config['headers'] ?? [],
                'env' => $env,
                'timeout' => (int) ($config['timeout'] ?? 30),
                'enabled' => true,
                'metadata' => [
                    'plugin_install_id' => $install->id,
                    'plugin_slug' => $install->slug,
                    'connector_key' => $connectorKey,
                    'credential_hints' => $credentialHints,
                ],
            ];

            if ($server !== null) {
                $server->update($payload);
            } else {
                $server = McpServer::create($payload);
            }

            $mcpSlugs[] = $server->slug;
        }

        return [
            'skill_ids' => $skillIds,
            'mcp_slugs' => $mcpSlugs,
        ];
    }

    public function mcpSlugFor(PluginInstall $install, string $connectorKey): string
    {
        return Str::slug($install->slug.'-'.$connectorKey);
    }

    protected function suggestVariableName(PluginInstall $install, string $envKey): string
    {
        $prefix = Str::upper(Str::slug($install->slug, '_'));

        return $prefix.'_'.Str::upper(Str::slug($envKey, '_'));
    }

    public function dematerialize(PluginInstall $install): void
    {
        $materialized = $install->materialized ?? [];

        foreach ($materialized['skill_ids'] ?? [] as $skillId) {
            $skill = SkillDefinition::query()->find($skillId);

            if ($skill !== null && ($skill->source_meta['plugin_install_id'] ?? null) == $install->id) {
                $skill->delete();
            }
        }

        foreach ($materialized['mcp_slugs'] ?? [] as $slug) {
            $server = McpServer::query()->where('slug', $slug)->first();

            if ($server !== null && ($server->metadata['plugin_install_id'] ?? null) == $install->id) {
                $server->delete();
            }
        }
    }
}
