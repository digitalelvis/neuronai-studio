<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Connectors;

use DigitalElvis\NeuronAIStudio\Models\McpServer;
use DigitalElvis\NeuronAIStudio\Models\PluginAccount;
use DigitalElvis\NeuronAIStudio\Models\PluginInstall;
use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;
use DigitalElvis\NeuronAIStudio\Plugins\PluginInstaller;
use Illuminate\Support\Str;
use DigitalElvis\NeuronAIStudio\Registry\ConnectorCatalog;
use DigitalElvis\NeuronAIStudio\Registry\McpRegistry;
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
    public function open(string $ref): void
    {
        $this->ref = $ref;
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

    public function openCredentials(?string $accountLabel = null): void
    {
        $this->dispatch('connector-open-credentials', ref: $this->ref, accountLabel: $accountLabel ?? 'default');
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
            $installId = (int) ($this->entry['install_id'] ?? $this->entry['entity_id'] ?? 0);

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
                            'label' => $account->label,
                            'connected' => $account->isConnected(),
                        ])
                        ->all();
                }
            }
        }

        if ($type === 'mcp') {
            $slug = (string) ($this->entry['slug'] ?? '');
            $server = app(McpRegistry::class)->find($slug);

            if ($server !== null) {
                $this->entry['transport'] = (string) ($server['transport'] ?? '');
                $this->entry['needs_auth'] = ! empty($server['token_env']) || ! empty($server['env']);
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
}
