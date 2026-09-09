<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Plugins;

use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Models\McpEndpoint;
use DigitalElvis\NeuronAIStudio\Models\McpServer;
use DigitalElvis\NeuronAIStudio\Models\ToolDefinition;
use DigitalElvis\NeuronAIStudio\Plugins\PluginInstaller;
use DigitalElvis\NeuronAIStudio\Plugins\PluginPolicy;
use DigitalElvis\NeuronAIStudio\Http\Livewire\Connectors\Detail;
use DigitalElvis\NeuronAIStudio\Registry\ConnectorCatalog;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class Index extends Component
{
    use WithFileUploads;

    #[Url]
    public string $view = 'catalog';

    #[Url]
    public ?string $connector = null;

    public bool $showCreateMenu = false;

    public ?string $createModal = null;

    public ?int $editEntityId = null;

    public ?string $createVectorStoreDriver = null;

    public $uploadFile = null;

    public string $githubUrl = '';

    public string $importError = '';

    public function mount(): void
    {
        if (! app(PluginPolicy::class)->enabled()) {
            abort(404);
        }

        if ($this->connector !== null) {
            $this->dispatch('connector-open-detail', connectorRef: $this->connector)->to(Detail::class);
        }
    }

    #[On('connector-switch-view')]
    public function switchView(string $view): void
    {
        $this->view = in_array($view, ['catalog', 'installed'], true) ? $view : 'catalog';
    }

    #[On('connector-install-plugin')]
    public function installPlugin(string $slug): void
    {
        try {
            $install = app(PluginInstaller::class)->installFromCatalogSlug($slug);
            session()->flash('success', __('neuronai-studio::plugins.installed', ['name' => $slug]));
            $this->dispatch('connector-catalog-refresh');
            $this->dispatch('connector-open-detail', connectorRef: 'plugin_install:'.$install->id)->to(Detail::class);
        } catch (Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    #[On('connector-open-create')]
    public function openCreate(string $type, mixed $entityId = null, ?string $vectorStoreDriver = null): void
    {
        $this->showCreateMenu = false;
        $this->createModal = $type;
        $this->editEntityId = is_numeric($entityId) ? (int) $entityId : null;
        $this->createVectorStoreDriver = $vectorStoreDriver;
    }

    #[On('connector-saved')]
    public function onConnectorSaved(?string $connectorRef = null): void
    {
        $this->closeCreateModal();
        $this->dispatch('connector-catalog-refresh');

        if ($connectorRef !== null) {
            $this->dispatch('connector-open-detail', connectorRef: $connectorRef)->to(Detail::class);
        }
    }

    public function openCreateModal(string $type): void
    {
        $this->showCreateMenu = false;
        $this->createModal = $type;
        $this->editEntityId = null;
        $this->createVectorStoreDriver = null;
        $this->importError = '';
    }

    public function closeCreateModal(): void
    {
        $this->createModal = null;
        $this->editEntityId = null;
        $this->createVectorStoreDriver = null;
        $this->uploadFile = null;
        $this->githubUrl = '';
        $this->importError = '';
    }

    public function showInstalled(): void
    {
        $this->view = 'installed';
    }

    public function showCatalog(): void
    {
        $this->view = 'catalog';
    }

    public function importUpload(): void
    {
        $this->importError = '';

        $this->validate([
            'uploadFile' => 'required|file|max:20480',
        ]);

        $path = $this->uploadFile->getRealPath();

        try {
            app(PluginInstaller::class)->installFromArchive((string) $path, ['source' => 'upload']);
            $this->closeCreateModal();
            session()->flash('success', __('neuronai-studio::plugins.upload_success'));
            $this->dispatch('connector-catalog-refresh');
        } catch (Throwable $e) {
            $this->importError = $e->getMessage();
        }
    }

    public function importGithub(): void
    {
        $this->importError = '';

        $this->validate([
            'githubUrl' => 'required|url',
        ]);

        try {
            app(PluginPolicy::class)->assertSourceAllowed([
                'slug' => '',
                'source' => 'github',
                'source_url' => $this->githubUrl,
            ]);
            app(PluginInstaller::class)->installFromGitHub($this->githubUrl);
            $this->closeCreateModal();
            session()->flash('success', __('neuronai-studio::plugins.github_success'));
            $this->dispatch('connector-catalog-refresh');
        } catch (Throwable $e) {
            $this->importError = $e->getMessage();
        }
    }

    public function render()
    {
        $installedCount = app(ConnectorCatalog::class)->installedEntries();
        $allowUpload = app(PluginPolicy::class)->mode() === 'allowlist';

        return view('neuronai-studio::livewire.plugins.index', [
            'installedCount' => count($installedCount),
            'allowUpload' => $allowUpload,
            'mcpEndpointsEnabled' => (bool) config('neuronai-studio.mcp_endpoints.enabled', false),
            'createModalParams' => $this->createModalParams(),
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [['label' => __('neuronai-studio::plugins.title')]],
            title: __('neuronai-studio::plugins.title'),
        ));
    }

    /** @return array<string, mixed> */
    protected function createModalParams(): array
    {
        if ($this->createModal === null) {
            return [];
        }

        return match ($this->createModal) {
            'mcp-server' => [
                'component' => 'neuronai-studio.mcp-servers.edit',
                'params' => ['server' => $this->editEntityId ? McpServer::find($this->editEntityId) : null, 'embedded' => true],
                'title' => __('neuronai-studio::connectors.create_mcp_custom'),
                'maxWidthClass' => 'max-w-4xl',
            ],
            'mcp-endpoint' => [
                'component' => 'neuronai-studio.mcp-endpoints.edit',
                'params' => ['endpoint' => $this->editEntityId ? McpEndpoint::find($this->editEntityId) : null, 'embedded' => true],
                'title' => __('neuronai-studio::connectors.create_mcp_endpoint'),
                'maxWidthClass' => 'max-w-4xl',
            ],
            'api' => [
                'component' => 'neuronai-studio.tools.edit',
                'params' => ['tool' => $this->editEntityId ? ToolDefinition::find($this->editEntityId) : null, 'embedded' => true, 'forcedToolKind' => 'webhook'],
                'title' => __('neuronai-studio::connectors.create_webhook'),
                'maxWidthClass' => 'max-w-4xl',
            ],
            'rag-tool' => [
                'component' => 'neuronai-studio.tools.edit',
                'params' => ['tool' => $this->editEntityId ? ToolDefinition::find($this->editEntityId) : null, 'embedded' => true, 'forcedToolKind' => 'rag'],
                'title' => __('neuronai-studio::connectors.create_rag_tool'),
                'maxWidthClass' => 'max-w-4xl',
            ],
            'rag' => [
                'component' => 'neuronai-studio.knowledge-bases.edit',
                'params' => [
                    'knowledgeBase' => $this->editEntityId ? KnowledgeBase::find($this->editEntityId) : null,
                    'embedded' => true,
                    'forcedVectorStoreDriver' => $this->createVectorStoreDriver,
                ],
                'title' => __('neuronai-studio::connectors.create_rag'),
                'maxWidthClass' => 'max-w-4xl',
            ],
            'mcp-json' => [
                'component' => 'neuronai-studio.connectors.import-mcp-json',
                'params' => ['embedded' => true],
                'title' => __('neuronai-studio::connectors.create_mcp_json'),
                'maxWidthClass' => 'max-w-2xl',
            ],
            default => [],
        };
    }
}
