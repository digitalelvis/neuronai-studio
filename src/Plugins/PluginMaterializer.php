<?php

namespace DigitalElvis\NeuronAIStudio\Plugins;

use DigitalElvis\NeuronAIStudio\Models\McpServer;
use DigitalElvis\NeuronAIStudio\Models\PluginInstall;
use DigitalElvis\NeuronAIStudio\Models\PluginPackage;
use DigitalElvis\NeuronAIStudio\Models\PluginPackageSkill;
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
     * Catalog install: shared package skills + tenant MCP rows.
     *
     * @return array{package_id: int, skill_refs: array<int, string>, mcp_slugs: array<int, string>}
     */
    public function materializeCatalog(PluginInstall $install, PluginPackage $package): array
    {
        $skillRefs = $package->skills
            ->map(fn (PluginPackageSkill $skill) => $skill->bindingRef())
            ->values()
            ->all();

        $mcpSlugs = $this->materializeMcpServers(
            $install,
            is_array($package->mcp_templates) ? $package->mcp_templates : [],
        );

        return [
            'package_id' => (int) $package->id,
            'skill_refs' => $skillRefs,
            'mcp_slugs' => $mcpSlugs,
        ];
    }

    /**
     * Allowlist / upload / ad-hoc GitHub: tenant-owned skill clones (legacy M22 path).
     *
     * @return array{skill_ids: array<int, int>, mcp_slugs: array<int, string>}
     */
    public function materializeOwned(PluginInstall $install, ParsedPlugin $parsed): array
    {
        $skillIds = [];

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

        $mcpSlugs = $this->materializeMcpServers($install, $parsed->mcpServers);

        return [
            'skill_ids' => $skillIds,
            'mcp_slugs' => $mcpSlugs,
        ];
    }

    /**
     * @deprecated Use materializeOwned() or materializeCatalog()
     *
     * @return array{skill_ids: array<int, int>, mcp_slugs: array<int, string>}
     */
    public function materialize(PluginInstall $install, ParsedPlugin $parsed): array
    {
        return $this->materializeOwned($install, $parsed);
    }

    /**
     * @param  array<string, array<string, mixed>>  $mcpServers
     * @return array<int, string>
     */
    protected function materializeMcpServers(PluginInstall $install, array $mcpServers): array
    {
        $mcpSlugs = [];

        foreach ($mcpServers as $connectorKey => $config) {
            if (! is_array($config)) {
                continue;
            }

            $transport = (string) ($config['transport'] ?? 'stdio');

            if ($transport === 'stdio') {
                if (! $this->policy->stdioAllowed()) {
                    throw new \Illuminate\Auth\Access\AuthorizationException(
                        "Plugin MCP connector [{$connectorKey}] uses stdio transport, which is disabled on this host."
                    );
                }

                $this->mcpRegistry->assertStdioCommandAllowed($config['command'] ?? null);
            }

            $slug = $this->mcpSlugFor($install, (string) $connectorKey);

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

            $server = $this->findMcpServerForInstall($install, $slug);

            $authMode = (string) ($config['auth'] ?? 'token');

            $payload = [
                'name' => Str::headline((string) $connectorKey).' ('.$install->name.')',
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
                    'auth' => $authMode !== '' ? $authMode : 'token',
                ],
            ];

            if ($server !== null) {
                $server->update($payload);
            } else {
                $server = McpServer::create($payload);
            }

            $mcpSlugs[] = $server->slug;
        }

        return $mcpSlugs;
    }

    public function mcpSlugFor(PluginInstall $install, string $connectorKey): string
    {
        return Str::slug($install->slug.'-'.$connectorKey);
    }

    protected function findMcpServerForInstall(PluginInstall $install, string $slug): ?McpServer
    {
        $query = McpServer::query()->where('slug', $slug);

        if ($install->tenant_id !== null && $install->tenant_id !== '') {
            return $query->where('tenant_id', $install->tenant_id)->first();
        }

        return $query->whereNull('tenant_id')->first();
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
