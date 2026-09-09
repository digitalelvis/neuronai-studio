<?php

namespace DigitalElvis\NeuronAIStudio\Registry;

use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Models\McpEndpoint;
use DigitalElvis\NeuronAIStudio\Models\McpServer;
use DigitalElvis\NeuronAIStudio\Models\PluginInstall;
use DigitalElvis\NeuronAIStudio\Models\ToolDefinition;
use DigitalElvis\NeuronAIStudio\Plugins\PluginPolicy;
use DigitalElvis\NeuronAIStudio\Support\ConnectorIconResolver;
use DigitalElvis\NeuronAIStudio\Support\StudioTranslator;
use Illuminate\Support\Str;

class ConnectorCatalog
{
    public function __construct(
        protected PluginCatalogRegistry $pluginCatalog,
        protected McpRegistry $mcpRegistry,
        protected ToolRegistry $toolRegistry,
        protected PluginPolicy $pluginPolicy,
        protected ConnectorIconResolver $icons,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function catalogEntries(): array
    {
        $entries = array_merge(
            $this->pluginEntries(),
            $this->mcpCatalogEntries(),
            $this->toolEntries(),
            $this->dataSourceEntries(),
        );

        return $this->sortEntries($entries);
    }

    /** @return array<int, array<string, mixed>> */
    public function installedEntries(): array
    {
        $entries = array_merge(
            $this->installedPluginEntries(),
            $this->installedMcpEntries(),
            $this->apiEntries(),
            $this->ragToolEntries(),
            $this->ragEntries(),
            $this->endpointEntries(),
        );

        return $this->sortEntries($entries);
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array<string, mixed>>
     */
    public function sortEntries(array $entries): array
    {
        usort($entries, function (array $a, array $b) {
            $featuredCompare = (($b['featured'] ?? false) ? 1 : 0) <=> (($a['featured'] ?? false) ? 1 : 0);

            if ($featuredCompare !== 0) {
                return $featuredCompare;
            }

            return strcasecmp((string) $a['name'], (string) $b['name']);
        });

        return $entries;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array<string, mixed>>
     */
    public function filter(array $entries, string $search = '', string $type = 'all'): array
    {
        if ($type !== '' && $type !== 'all') {
            $entries = array_values(array_filter(
                $entries,
                fn (array $entry) => ($entry['type'] ?? '') === $type
            ));
        }

        if ($search === '') {
            return $entries;
        }

        $needle = strtolower($search);

        return array_values(array_filter($entries, function (array $entry) use ($needle) {
            $haystack = strtolower(implode(' ', array_filter([
                $entry['ref'] ?? '',
                $entry['slug'] ?? '',
                $entry['name'] ?? '',
                $entry['description'] ?? '',
            ])));

            return str_contains($haystack, $needle);
        }));
    }

    /** @return array<string, mixed>|null */
    public function find(string $ref): ?array
    {
        foreach (array_merge($this->catalogEntries(), $this->installedEntries()) as $entry) {
            if (($entry['ref'] ?? '') === $ref) {
                return $entry;
            }
        }

        return null;
    }

    /** @return array<int, string> */
    public function catalogFilterTypes(): array
    {
        return ['all', 'plugin', 'mcp', 'tool', 'data_source'];
    }

    /** @return array<int, string> */
    public function installedFilterTypes(): array
    {
        return ['all', 'plugin', 'mcp', 'api', 'rag_tool', 'rag', 'endpoint'];
    }

    /** @return array<int, array<string, mixed>> */
    protected function pluginEntries(): array
    {
        if (! $this->pluginPolicy->enabled()) {
            return [];
        }

        $installed = PluginInstall::query()
            ->where('status', PluginInstall::STATUS_INSTALLED)
            ->get(['id', 'slug'])
            ->keyBy('slug');

        $entries = [];

        foreach ($this->pluginCatalog->listings() as $listing) {
            $slug = (string) ($listing['slug'] ?? '');
            $install = $installed->get($slug);

            $entries[] = [
                'ref' => 'plugin:'.$slug,
                'type' => 'plugin',
                'slug' => $slug,
                'name' => (string) ($listing['title'] ?? $listing['name'] ?? Str::headline($slug)),
                'description' => (string) ($listing['description'] ?? ''),
                'featured' => (bool) ($listing['featured'] ?? false),
                'installed' => $install !== null,
                'install_id' => $install?->id,
                'entity_id' => $install?->id,
                'icon' => $this->icons->resolvePluginPath(
                    (string) ($listing['path'] ?? ''),
                    isset($listing['icon']) ? (string) $listing['icon'] : null,
                ),
                'categories' => is_array($listing['categories'] ?? null) ? $listing['categories'] : [],
                'author' => 'host',
                'version' => (string) ($listing['version'] ?? ''),
            ];
        }

        return $entries;
    }

    /** @return array<int, array<string, mixed>> */
    protected function installedPluginEntries(): array
    {
        if (! $this->pluginPolicy->enabled()) {
            return [];
        }

        return PluginInstall::query()
            ->where('status', PluginInstall::STATUS_INSTALLED)
            ->orderBy('name')
            ->get()
            ->map(function (PluginInstall $install) {
                $listing = $this->pluginCatalog->find($install->slug);

                return [
                    'ref' => 'plugin_install:'.$install->id,
                    'type' => 'plugin',
                    'slug' => $install->slug,
                    'name' => $install->name,
                    'description' => (string) $install->description,
                    'featured' => false,
                    'installed' => true,
                    'install_id' => $install->id,
                    'entity_id' => $install->id,
                    'icon' => $this->icons->resolvePluginPath(
                        is_array($listing) ? (string) ($listing['path'] ?? '') : '',
                        is_array($listing) && isset($listing['icon']) ? (string) $listing['icon'] : null,
                    ),
                    'categories' => [],
                    'author' => (string) ($install->source ?? 'host'),
                    'version' => (string) ($install->version ?? ''),
                ];
            })
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    protected function mcpCatalogEntries(): array
    {
        $entries = [];

        foreach ($this->mcpRegistry->all(includeDisabled: true) as $slug => $server) {
            if ($this->isPluginOwnedMcp($server)) {
                continue;
            }

            $entries[] = [
                'ref' => 'mcp:'.$slug,
                'type' => 'mcp',
                'slug' => $slug,
                'name' => (string) ($server['label'] ?? Str::headline($slug)),
                'description' => (string) ($server['description'] ?? ''),
                'featured' => false,
                'installed' => ($server['source'] ?? '') === 'database' || ($server['enabled'] ?? true),
                'install_id' => null,
                'entity_id' => $server['id'] ?? null,
                'icon' => $this->resolveMcpIcon($server),
                'categories' => [],
                'author' => (string) ($server['source'] ?? 'host'),
                'transport' => (string) ($server['transport'] ?? ''),
            ];
        }

        return $entries;
    }

    /** @return array<int, array<string, mixed>> */
    protected function installedMcpEntries(): array
    {
        $entries = [];

        foreach ($this->mcpRegistry->all(includeDisabled: true) as $slug => $server) {
            if (($server['source'] ?? '') !== 'database' && ! $this->isPluginOwnedMcp($server)) {
                continue;
            }

            if (($server['source'] ?? '') === 'config' && ! $this->isPluginOwnedMcp($server)) {
                continue;
            }

            $entries[] = [
                'ref' => 'mcp:'.$slug,
                'type' => 'mcp',
                'slug' => $slug,
                'name' => (string) ($server['label'] ?? Str::headline($slug)),
                'description' => (string) ($server['description'] ?? ''),
                'featured' => false,
                'installed' => true,
                'install_id' => is_array($server['metadata'] ?? null) ? ($server['metadata']['plugin_install_id'] ?? null) : null,
                'entity_id' => $server['id'] ?? null,
                'icon' => $this->resolveMcpIcon($server),
                'categories' => [],
                'author' => $this->isPluginOwnedMcp($server) ? 'plugin' : (string) ($server['source'] ?? 'host'),
                'transport' => (string) ($server['transport'] ?? ''),
            ];
        }

        return $entries;
    }

    /** @return array<int, array<string, mixed>> */
    protected function toolEntries(): array
    {
        return collect($this->toolRegistry->all())
            ->map(function (array $tool) {
                $ref = (string) ($tool['ref'] ?? '');

                return [
                    'ref' => 'tool:'.$ref,
                    'type' => 'tool',
                    'slug' => $ref,
                    'name' => (string) ($tool['label'] ?? ''),
                    'description' => (string) ($tool['description'] ?? ''),
                    'featured' => false,
                    'installed' => true,
                    'install_id' => null,
                    'entity_id' => null,
                    'icon' => $this->icons->resolve(
                        config('neuronai-studio.connectors.icons.tools.'.$ref)
                        ?? config('neuronai-studio.tools.'.Str::after($ref, 'toolkit:').'.icon')
                    ),
                    'categories' => [(string) ($tool['category'] ?? 'builtin')],
                    'author' => 'host',
                    'tool_type' => (string) ($tool['type'] ?? ''),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    protected function dataSourceEntries(): array
    {
        $entries = [];

        foreach ((array) config('neuronai-studio.rag.vector_stores', []) as $driver => $store) {
            if (! is_array($store)) {
                continue;
            }

            $label = StudioTranslator::get(
                'registry.vector_stores.'.$driver,
                (string) ($store['label'] ?? Str::headline($driver))
            );

            $entries[] = [
                'ref' => 'data_source:'.$driver,
                'type' => 'data_source',
                'slug' => (string) $driver,
                'name' => $label,
                'description' => (string) ($store['description'] ?? ''),
                'featured' => (bool) ($store['featured'] ?? false),
                'installed' => false,
                'install_id' => null,
                'entity_id' => null,
                'icon' => $this->icons->resolve(
                    (string) ($store['icon'] ?? config('neuronai-studio.connectors.icons.data_sources.'.$driver, ''))
                ),
                'categories' => [],
                'author' => 'studio',
                'vector_store_driver' => (string) $driver,
            ];
        }

        return $entries;
    }

    /** @return array<int, array<string, mixed>> */
    protected function apiEntries(): array
    {
        return ToolDefinition::query()
            ->where('type', 'webhook')
            ->orderBy('name')
            ->get()
            ->map(fn (ToolDefinition $tool) => $this->toolDefinitionEntry($tool, 'api'))
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    protected function ragToolEntries(): array
    {
        return ToolDefinition::query()
            ->where('type', 'rag')
            ->orderBy('name')
            ->get()
            ->map(fn (ToolDefinition $tool) => $this->toolDefinitionEntry($tool, 'rag_tool'))
            ->all();
    }

    /** @return array<string, mixed> */
    protected function toolDefinitionEntry(ToolDefinition $tool, string $type): array
    {
        $metadata = is_array($tool->metadata) ? $tool->metadata : [];

        return [
            'ref' => $type.':'.$tool->id,
            'type' => $type,
            'slug' => $tool->slug,
            'name' => $tool->name,
            'description' => (string) $tool->description,
            'featured' => false,
            'installed' => true,
            'install_id' => null,
            'entity_id' => $tool->id,
            'icon' => $this->icons->resolve(isset($metadata['icon']) ? (string) $metadata['icon'] : null),
            'categories' => [],
            'author' => 'studio',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    protected function ragEntries(): array
    {
        return KnowledgeBase::query()
            ->orderBy('name')
            ->get()
            ->map(function (KnowledgeBase $kb) {
                $metadata = is_array($kb->metadata) ? $kb->metadata : [];

                return [
                    'ref' => 'rag:'.$kb->id,
                    'type' => 'rag',
                    'slug' => $kb->slug,
                    'name' => $kb->name,
                    'description' => (string) $kb->description,
                    'featured' => false,
                    'installed' => true,
                    'install_id' => null,
                    'entity_id' => $kb->id,
                    'icon' => $this->icons->resolve(isset($metadata['icon']) ? (string) $metadata['icon'] : null),
                    'categories' => [],
                    'author' => (string) ($kb->source ?? 'studio'),
                    'vector_store_driver' => $kb->vectorStoreDriver(),
                ];
            })
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    protected function endpointEntries(): array
    {
        if (! config('neuronai-studio.mcp_endpoints.enabled', false)) {
            return [];
        }

        return McpEndpoint::query()
            ->orderBy('name')
            ->get()
            ->map(function (McpEndpoint $endpoint) {
                $config = is_array($endpoint->config) ? $endpoint->config : [];

                return [
                    'ref' => 'endpoint:'.$endpoint->id,
                    'type' => 'endpoint',
                    'slug' => $endpoint->slug,
                    'name' => $endpoint->name,
                    'description' => (string) $endpoint->description,
                    'featured' => false,
                    'installed' => true,
                    'install_id' => null,
                    'entity_id' => $endpoint->id,
                    'icon' => $this->icons->resolve(isset($config['icon']) ? (string) $config['icon'] : null),
                    'categories' => [],
                    'author' => 'studio',
                    'enabled' => (bool) $endpoint->enabled,
                ];
            })
            ->all();
    }

    /** @param  array<string, mixed>  $server */
    protected function resolveMcpIcon(array $server): ?string
    {
        $metadata = is_array($server['metadata'] ?? null) ? $server['metadata'] : [];
        $slug = (string) ($server['slug'] ?? '');

        return $this->icons->resolve(
            (isset($metadata['icon']) ? (string) $metadata['icon'] : null)
            ?? config('neuronai-studio.connectors.icons.mcp.'.$slug)
            ?? config('neuronai-studio.mcp_servers.'.$slug.'.icon')
        );
    }

    /** @param  array<string, mixed>  $server */
    protected function isPluginOwnedMcp(array $server): bool
    {
        $metadata = $server['metadata'] ?? null;

        return is_array($metadata) && ! empty($metadata['plugin_install_id']);
    }
}
