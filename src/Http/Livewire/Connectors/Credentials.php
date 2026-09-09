<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Connectors;

use DigitalElvis\NeuronAIStudio\Models\McpServer;
use DigitalElvis\NeuronAIStudio\Models\PluginAccount;
use DigitalElvis\NeuronAIStudio\Models\PluginInstall;
use DigitalElvis\NeuronAIStudio\Models\Variable;
use DigitalElvis\NeuronAIStudio\Plugins\PluginAccountService;
use DigitalElvis\NeuronAIStudio\Registry\McpRegistry;
use Livewire\Attributes\On;
use Livewire\Component;

class Credentials extends Component
{
    public ?string $ref = null;

    public string $accountLabel = 'default';

    /** @var array<string, string> */
    public array $credentialMap = [];

    public string $tokenEnv = '';

    public string $mode = 'plugin';

    #[On('connector-open-credentials')]
    public function open(string $ref, ?string $accountLabel = null): void
    {
        $this->ref = $ref;
        $this->accountLabel = $accountLabel ?? 'default';
        $this->credentialMap = [];
        $this->tokenEnv = '';
        $this->mode = 'plugin';

        if (str_starts_with($ref, 'plugin_install:') || str_starts_with($ref, 'plugin:')) {
            $this->loadPluginCredentials($ref);
        } elseif (str_starts_with($ref, 'mcp:')) {
            $this->loadMcpCredentials($ref);
        }
    }

    public function close(): void
    {
        $this->ref = null;
        $this->credentialMap = [];
        $this->tokenEnv = '';
    }

    public function save(): void
    {
        if ($this->mode === 'plugin') {
            $this->savePluginCredentials();
        } elseif ($this->mode === 'mcp') {
            $this->saveMcpCredentials();
        }
    }

    protected function loadPluginCredentials(string $ref): void
    {
        $this->mode = 'plugin';
        $install = $this->resolvePluginInstall($ref);

        if ($install === null) {
            return;
        }

        $account = $install->accounts()->where('label', $this->accountLabel)->first();

        if ($account !== null) {
            $this->credentialMap = is_array($account->credential_map) ? $account->credential_map : [];
        }
    }

    protected function loadMcpCredentials(string $ref): void
    {
        $this->mode = 'mcp';
        $slug = substr($ref, strlen('mcp:'));
        $server = McpServer::query()->where('slug', $slug)->first();

        if ($server === null) {
            $config = app(McpRegistry::class)->find($slug);
            $this->tokenEnv = (string) ($config['token_env'] ?? '');

            return;
        }

        $this->tokenEnv = (string) ($server->token_env ?? '');
    }

    protected function savePluginCredentials(): void
    {
        $install = $this->resolvePluginInstall((string) $this->ref);

        if ($install === null) {
            return;
        }

        $account = $install->accounts()->where('label', $this->accountLabel)->first();

        if ($account === null) {
            return;
        }

        app(PluginAccountService::class)->updateCredentialMap($account, $this->credentialMap);

        session()->flash('success', __('neuronai-studio::plugins.credentials_saved'));
        $this->close();
        $this->dispatch('connector-catalog-refresh');
        $this->dispatch('connector-open-detail', ref: 'plugin_install:'.$install->id)->to(Detail::class);
    }

    protected function saveMcpCredentials(): void
    {
        $slug = substr((string) $this->ref, strlen('mcp:'));
        $server = McpServer::query()->where('slug', $slug)->first();

        if ($server === null) {
            return;
        }

        $server->update(['token_env' => $this->tokenEnv ?: null]);

        session()->flash('success', __('neuronai-studio::plugins.credentials_saved'));
        $this->close();
        $this->dispatch('connector-catalog-refresh');
        $this->dispatch('connector-open-detail', ref: 'mcp:'.$slug)->to(Detail::class);
    }

    protected function resolvePluginInstall(string $ref): ?PluginInstall
    {
        if (str_starts_with($ref, 'plugin_install:')) {
            $id = (int) substr($ref, strlen('plugin_install:'));

            return PluginInstall::query()->find($id);
        }

        if (str_starts_with($ref, 'plugin:')) {
            $slug = substr($ref, strlen('plugin:'));

            return PluginInstall::query()
                ->where('slug', $slug)
                ->where('status', PluginInstall::STATUS_INSTALLED)
                ->first();
        }

        return null;
    }

    public function render()
    {
        return view('neuronai-studio::livewire.connectors.credentials', [
            'isOpen' => $this->ref !== null,
            'variables' => Variable::query()->orderBy('name')->pluck('name')->all(),
        ]);
    }
}
