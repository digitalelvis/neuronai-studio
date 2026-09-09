<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Connectors;

use DigitalElvis\NeuronAIStudio\Models\McpServer;
use DigitalElvis\NeuronAIStudio\Models\PluginAccount;
use DigitalElvis\NeuronAIStudio\Models\PluginInstall;
use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;
use DigitalElvis\NeuronAIStudio\Plugins\OAuth\PluginOAuthRegistry;
use DigitalElvis\NeuronAIStudio\Plugins\PluginAccountService;
use DigitalElvis\NeuronAIStudio\Plugins\PluginInstaller;
use DigitalElvis\NeuronAIStudio\Plugins\PluginManifestParser;
use Illuminate\Support\Str;
use DigitalElvis\NeuronAIStudio\Registry\ConnectorCatalog;
use DigitalElvis\NeuronAIStudio\Registry\McpRegistry;
use DigitalElvis\NeuronAIStudio\Registry\PluginCatalogRegistry;
use DigitalElvis\NeuronAIStudio\Registry\ToolRegistry;
use Livewire\Attributes\On;
use Livewire\Component;

class Detail extends Component
{
    public ?string $ref = null;

    /** @var array<string, mixed> */
    public array $entry = [];

    /** @var array<int, array<string, mixed>> */
    public array $skills = [];

    /** @var array<int, array<string, mixed>> */
    public array $mcpConnectors = [];

    /** @var array<int, array<string, mixed>> */
    public array $accounts = [];

    public function mount(?string $ref = null): void
    {
        if ($ref !== null) {
            $this->open($ref);
        }
    }

    #[On('connector-open-detail')]
    public function open(string $connectorRef): void
    {
        $this->ref = $connectorRef;
        $this->loadEntry();
    }

    public function close(): void
    {
        $this->ref = null;
        $this->entry = [];
        $this->skills = [];
        $this->mcpConnectors = [];
        $this->accounts = [];
        $this->dispatch('connector-detail-closed');
    }

    public function installPlugin(): void
    {
        $slug = (string) ($this->entry['slug'] ?? '');

        if ($slug === '') {
            return;
        }

        $this->dispatch('connector-install-plugin', slug: $slug);
    }

    public function uninstallPlugin(): void
    {
        $installId = (int) ($this->entry['install_id'] ?? $this->entry['entity_id'] ?? 0);

        if ($installId <= 0) {
            return;
        }

        $install = PluginInstall::query()->find($installId);

        if ($install === null || ! $install->isInstalled()) {
            return;
        }

        app(PluginInstaller::class)->uninstall($install);

        session()->flash('success', __('neuronai-studio::plugins.uninstalled', ['name' => $install->name]));
        $this->close();
        $this->dispatch('connector-catalog-refresh');
    }

    #[On('connector-catalog-refresh')]
    public function refreshEntry(): void
    {
        if ($this->ref !== null) {
            $this->loadEntry();
        }
    }

    public function disconnectAccount(int $accountId): void
    {
        $installId = (int) ($this->entry['install_id'] ?? $this->entry['entity_id'] ?? 0);

        if ($installId <= 0 || $accountId <= 0) {
            return;
        }

        $account = PluginAccount::query()->find($accountId);

        if ($account === null || $account->plugin_install_id !== $installId || ! $account->isConnected()) {
            return;
        }

        app(PluginAccountService::class)->disconnect($account);

        session()->flash('success', __('neuronai-studio::plugins.disconnected', [
            'name' => (string) ($this->entry['name'] ?? $account->label),
        ]));

        $this->loadEntry();
        $this->dispatch('connector-catalog-refresh');
    }

    public function startOAuth(int $accountId): void
    {
        $slug = (string) ($this->entry['slug'] ?? '');
        $installId = (int) ($this->entry['install_id'] ?? $this->entry['entity_id'] ?? 0);

        if ($slug === '' || $installId <= 0 || $accountId <= 0) {
            return;
        }

        $this->redirectRoute('neuronai-studio.plugins.oauth.authorize', [
            'slug' => $slug,
            'install' => $installId,
            'account' => $accountId,
        ]);
    }

    public function openCredentials(?string $accountLabel = null): void
    {
        $this->dispatch('connector-open-credentials', connectorRef: $this->ref, accountLabel: $accountLabel ?? 'default');
    }

    public function openEdit(): void
    {
        $type = (string) ($this->entry['type'] ?? '');
        $entityId = $this->entry['entity_id'] ?? null;

        if ($type === 'data_source') {
            $this->dispatch(
                'connector-open-create',
                type: 'rag',
                vectorStoreDriver: (string) ($this->entry['vector_store_driver'] ?? $this->entry['slug'] ?? ''),
            );

            return;
        }

        $this->dispatch('connector-open-create', type: $this->createTypeFor($type), entityId: $entityId);
    }

    protected function createTypeFor(string $type): string
    {
        return match ($type) {
            'mcp' => 'mcp-server',
            'api' => 'api',
            'rag_tool' => 'rag-tool',
            'rag' => 'rag',
            'endpoint' => 'mcp-endpoint',
            default => $type,
        };
    }

    protected function loadEntry(): void
    {
        if ($this->ref === null) {
            return;
        }

        $catalog = app(ConnectorCatalog::class);
        $this->entry = $catalog->find($this->ref) ?? [];
        $this->skills = [];
        $this->mcpConnectors = [];
        $this->accounts = [];

        if ($this->entry === []) {
            return;
        }

        $type = (string) ($this->entry['type'] ?? '');

        if ($type === 'plugin') {
            $slug = (string) ($this->entry['slug'] ?? '');
            $installId = (int) ($this->entry['install_id'] ?? $this->entry['entity_id'] ?? 0);
            $install = null;

            if ($installId <= 0 && str_starts_with((string) $this->ref, 'plugin:')) {
                $slug = Str::after((string) $this->ref, 'plugin:');
                $resolved = PluginInstall::query()
                    ->where('slug', $slug)
                    ->where('status', PluginInstall::STATUS_INSTALLED)
                    ->first();

                if ($resolved !== null) {
                    $installId = $resolved->id;
                    $this->entry['install_id'] = $resolved->id;
                    $this->entry['installed'] = true;
                }
            }

            if ($installId > 0) {
                $install = PluginInstall::query()->find($installId);

                if ($install !== null) {
                    $slug = (string) ($this->entry['slug'] ?? $install->slug);
                    $this->entry['auth_mode'] = $this->resolvePluginAuthMode($install, $slug);
                    $authMode = (string) ($this->entry['auth_mode'] ?? 'token');
                    $oauthConfigured = app(PluginOAuthRegistry::class)->isConfigured($slug);
                    $this->entry['oauth_configured'] = $oauthConfigured;

                    if ($install->usesSharedPackage()) {
                        $install->loadMissing('package.skills');
                        $this->skills = ($install->package?->skills ?? collect())
                            ->sortBy('slug')
                            ->values()
                            ->map(fn ($skill) => [
                                'slug' => $skill->slug(),
                                'name' => $skill->slug(),
                                'description' => $skill->description(),
                            ])
                            ->all();
                    } else {
                        $this->skills = SkillDefinition::query()
                            ->whereIn('id', $install->materializedSkillIds())
                            ->orderBy('slug')
                            ->get(['id', 'slug', 'display_name', 'description'])
                            ->map(fn (SkillDefinition $skill) => [
                                'slug' => $skill->slug,
                                'name' => $skill->displayName(),
                                'description' => (string) $skill->description,
                            ])
                            ->all();
                    }

                    $this->mcpConnectors = McpServer::query()
                        ->whereIn('slug', $install->materializedMcpSlugs())
                        ->orderBy('slug')
                        ->get(['slug', 'name', 'transport'])
                        ->map(fn (McpServer $server) => [
                            'slug' => $server->slug,
                            'name' => $server->name,
                            'transport' => $server->transport,
                        ])
                        ->all();

                    $this->accounts = $install->accounts()
                        ->orderBy('label')
                        ->get()
                        ->map(fn (PluginAccount $account) => [
                            'id' => $account->id,
                            'label' => $account->label,
                            'connected' => $account->isConnected(),
                            'supports_oauth' => in_array($authMode, ['oauth', 'oauth_or_token'], true),
                            'supports_manual_auth' => in_array($authMode, ['token', 'oauth_or_token'], true),
                        ])
                        ->all();
                }
            }

            if (! isset($this->entry['auth_mode'])) {
                $this->entry['auth_mode'] = $this->resolvePluginAuthMode($install, $slug);
            }
        }

        if ($type === 'mcp') {
            $slug = (string) ($this->entry['slug'] ?? '');
            $server = app(McpRegistry::class)->find($slug);

            if ($server !== null) {
                $this->entry['transport'] = (string) ($server['transport'] ?? '');
                $this->entry['needs_auth'] = ! empty($server['token_env']) || ! empty($server['env']);
                $metadata = is_array($server['metadata'] ?? null) ? $server['metadata'] : [];
                $this->entry['auth_mode'] = (string) ($metadata['auth'] ?? 'token');
            }
        }

        if ($type === 'tool') {
            $toolRef = Str::startsWith((string) ($this->entry['ref'] ?? ''), 'tool:')
                ? Str::after((string) $this->entry['ref'], 'tool:')
                : (string) ($this->entry['slug'] ?? '');

            $tool = app(ToolRegistry::class)->find($toolRef);
            $this->entry['tool_ref'] = $toolRef;
            $this->entry['tool_type'] = (string) ($tool['type'] ?? '');
        }
    }

    public function render()
    {
        return view('neuronai-studio::livewire.connectors.detail', [
            'isOpen' => $this->ref !== null && $this->entry !== [],
        ]);
    }

    protected function resolvePluginAuthMode(?PluginInstall $install, string $slug): string
    {
        if ($install !== null) {
            $modes = McpServer::query()
                ->whereIn('slug', $install->materializedMcpSlugs())
                ->get()
                ->map(fn (McpServer $server) => (string) (($server->metadata ?? [])['auth'] ?? 'token'))
                ->filter()
                ->unique()
                ->values();

            if ($modes->contains('oauth')) {
                return 'oauth';
            }

            if ($modes->contains('oauth_or_token')) {
                return 'oauth_or_token';
            }
        }

        if ($slug === '') {
            return 'token';
        }

        $listing = app(PluginCatalogRegistry::class)->find($slug);

        if (! is_array($listing) || empty($listing['path']) || ! is_dir($listing['path'])) {
            return 'token';
        }

        try {
            $parsed = app(PluginManifestParser::class)->parseRoot((string) $listing['path']);

            foreach ($parsed->mcpServers as $config) {
                return (string) ($config['auth'] ?? 'token');
            }
        } catch (\Throwable) {
            return 'token';
        }

        return 'token';
    }
}
