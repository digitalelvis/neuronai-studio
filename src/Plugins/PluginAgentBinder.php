<?php

namespace DigitalElvis\NeuronAIStudio\Plugins;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\AgentMcpServer;
use DigitalElvis\NeuronAIStudio\Models\AgentPluginBinding;
use DigitalElvis\NeuronAIStudio\Models\McpServer;
use DigitalElvis\NeuronAIStudio\Models\PluginAccount;
use DigitalElvis\NeuronAIStudio\Models\PluginInstall;
use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;

class PluginAgentBinder
{
    /**
     * @param  array<int, array{install_id: int, account_id?: int|null}>  $bindings
     */
    public function syncAgent(AgentDefinition $agent, array $bindings): void
    {
        $normalized = [];

        foreach ($bindings as $binding) {
            $installId = (int) ($binding['install_id'] ?? 0);

            if ($installId <= 0) {
                continue;
            }

            $normalized[$installId] = [
                'install_id' => $installId,
                'account_id' => isset($binding['account_id']) ? (int) $binding['account_id'] : null,
            ];
        }

        $existing = AgentPluginBinding::query()
            ->where('agent_definition_id', $agent->id)
            ->get()
            ->keyBy('plugin_install_id');

        foreach ($normalized as $installId => $payload) {
            $install = PluginInstall::query()->find($installId);

            if ($install === null || ! $install->isInstalled()) {
                continue;
            }

            $accountId = $payload['account_id'];

            if ($accountId === null || $accountId <= 0) {
                $accountId = $install->accounts()->where('label', 'default')->value('id');
            }

            AgentPluginBinding::updateOrCreate(
                [
                    'agent_definition_id' => $agent->id,
                    'plugin_install_id' => $installId,
                ],
                [
                    'plugin_account_id' => $accountId,
                ],
            );
        }

        foreach ($existing as $installId => $row) {
            if (! isset($normalized[$installId])) {
                $row->delete();
            }
        }

        $this->syncSkillsAndMcp($agent);
    }

    public function syncSkillsAndMcp(AgentDefinition $agent): void
    {
        $agent->loadMissing('pluginBindings.install');

        $boundInstallIds = $agent->pluginBindings
            ->pluck('plugin_install_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $skills = is_array($agent->skills) ? $agent->skills : [];
        $boundPackageIds = [];

        foreach ($agent->pluginBindings as $binding) {
            $packageId = $binding->install?->package_id;
            if ($packageId !== null) {
                $boundPackageIds[(int) $packageId] = true;
            }
        }

        $skills = array_values(array_filter($skills, function ($skill) use ($boundInstallIds, $boundPackageIds) {
            if (! is_array($skill) || empty($skill['ref'])) {
                return false;
            }

            $ref = (string) $skill['ref'];

            if (str_starts_with($ref, 'skill:pkg:')) {
                $remainder = substr($ref, strlen('skill:pkg:'));
                $packageId = (int) explode(':', $remainder, 2)[0];

                return isset($boundPackageIds[$packageId]);
            }

            if (! str_starts_with($ref, 'skill:db:')) {
                return true;
            }

            $skillId = (int) substr($ref, strlen('skill:db:'));
            $definition = SkillDefinition::query()->find($skillId);
            $installId = is_array($definition?->source_meta) ? ($definition->source_meta['plugin_install_id'] ?? null) : null;

            if ($installId === null) {
                return true;
            }

            return in_array((int) $installId, $boundInstallIds, true);
        }));

        foreach ($agent->pluginBindings as $binding) {
            $install = $binding->install;

            if ($install === null) {
                continue;
            }

            foreach ($install->materializedSkillRefs() as $ref) {
                $skills[] = ['ref' => $ref];
            }

            foreach ($install->materializedSkillIds() as $skillId) {
                $skills[] = ['ref' => 'skill:db:'.$skillId];
            }
        }

        $agent->update([
            'skills' => $this->uniqueSkillRefs($skills),
        ]);

        $manualMcpSlugs = [];

        foreach ($agent->mcpBindings as $binding) {
            $server = McpServer::query()->where('slug', $binding->mcp_server_slug)->first();
            $installId = is_array($server?->metadata) ? ($server->metadata['plugin_install_id'] ?? null) : null;

            if ($installId === null) {
                $manualMcpSlugs[] = $binding->mcp_server_slug;
            }
        }

        $pluginMcpSlugs = [];

        foreach ($agent->pluginBindings as $binding) {
            $install = $binding->install;

            if ($install === null) {
                continue;
            }

            foreach ($install->materializedMcpSlugs() as $slug) {
                $pluginMcpSlugs[] = $slug;
            }
        }

        $this->syncMcpPivot($agent, array_values(array_unique(array_merge($manualMcpSlugs, $pluginMcpSlugs))));
    }

    /**
     * @param  array<int, array{ref: string}>  $refs
     * @return array<int, array{ref: string}>
     */
    protected function uniqueSkillRefs(array $refs): array
    {
        $seen = [];
        $out = [];

        foreach ($refs as $ref) {
            $key = (string) ($ref['ref'] ?? '');

            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $out[] = ['ref' => $key];
        }

        return $out;
    }

    /** @param  array<int, string>  $slugs */
    protected function syncMcpPivot(AgentDefinition $agent, array $slugs): void
    {
        $registry = app(\DigitalElvis\NeuronAIStudio\Registry\McpRegistry::class);
        $keep = [];

        foreach ($slugs as $slug) {
            if ($registry->find($slug) === null) {
                continue;
            }

            $keep[$slug] = true;

            AgentMcpServer::updateOrCreate(
                [
                    'agent_definition_id' => $agent->id,
                    'mcp_server_slug' => $slug,
                ],
                [
                    'mcp_server_id' => McpServer::query()->where('slug', $slug)->value('id'),
                ],
            );
        }

        AgentMcpServer::query()
            ->where('agent_definition_id', $agent->id)
            ->get()
            ->each(function (AgentMcpServer $binding) use ($keep) {
                if (! isset($keep[$binding->mcp_server_slug])) {
                    $server = McpServer::query()->where('slug', $binding->mcp_server_slug)->first();
                    $installId = is_array($server?->metadata) ? ($server->metadata['plugin_install_id'] ?? null) : null;

                    if ($installId !== null) {
                        $binding->delete();
                    }
                }
            });
    }
}
