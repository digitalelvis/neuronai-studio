<x-neuronai-studio::ui.page>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('neuronai-studio::plugins.title') }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">{{ __('neuronai-studio::plugins.subtitle') }}</p>
            <p class="text-xs text-muted-foreground">{{ __('neuronai-studio::plugins.installed_count', ['count' => $installedCount]) }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($view === 'catalog')
                <x-neuronai-studio::ui.button variant="outline" wire:click="showInstalled">{{ __('neuronai-studio::connectors.my_connectors') }}</x-neuronai-studio::ui.button>
            @else
                <x-neuronai-studio::ui.button variant="outline" wire:click="showCatalog">{{ __('neuronai-studio::connectors.browse_catalog') }}</x-neuronai-studio::ui.button>
            @endif

            <div class="relative" x-data="{ open: @entangle('showCreateMenu') }">
                <x-neuronai-studio::ui.button type="button" @click="open = !open">
                    {{ __('neuronai-studio::connectors.create') }} ▾
                </x-neuronai-studio::ui.button>
                <div
                    x-show="open"
                    x-cloak
                    @click.outside="open = false"
                    class="absolute right-0 z-20 mt-2 w-56 rounded-md border border-border bg-background p-1 shadow-lg"
                >
                    @if ($mcpEndpointsEnabled)
                        <button type="button" class="block w-full rounded-sm px-3 py-2 text-left text-sm hover:bg-accent" wire:click="openCreateModal('mcp-endpoint')">{{ __('neuronai-studio::connectors.create_mcp_endpoint') }}</button>
                    @endif
                    <button type="button" class="block w-full rounded-sm px-3 py-2 text-left text-sm hover:bg-accent" wire:click="openCreateModal('mcp-json')">{{ __('neuronai-studio::connectors.create_mcp_json') }}</button>
                    <button type="button" class="block w-full rounded-sm px-3 py-2 text-left text-sm hover:bg-accent" wire:click="openCreateModal('mcp-server')">{{ __('neuronai-studio::connectors.create_mcp_custom') }}</button>
                    <button type="button" class="block w-full rounded-sm px-3 py-2 text-left text-sm hover:bg-accent" wire:click="openCreateModal('rag')">{{ __('neuronai-studio::connectors.create_rag') }}</button>
                    <button type="button" class="block w-full rounded-sm px-3 py-2 text-left text-sm hover:bg-accent" wire:click="openCreateModal('rag-tool')">{{ __('neuronai-studio::connectors.create_rag_tool') }}</button>
                    <button type="button" class="block w-full rounded-sm px-3 py-2 text-left text-sm hover:bg-accent" wire:click="openCreateModal('api')">{{ __('neuronai-studio::connectors.create_webhook') }}</button>
                    @if ($allowUpload)
                        <button type="button" class="block w-full rounded-sm px-3 py-2 text-left text-sm hover:bg-accent" wire:click="$set('createModal', 'plugin-upload'); $set('showCreateMenu', false)">{{ __('neuronai-studio::connectors.create_plugin_upload') }}</button>
                        <button type="button" class="block w-full rounded-sm px-3 py-2 text-left text-sm hover:bg-accent" wire:click="$set('createModal', 'plugin-github'); $set('showCreateMenu', false)">{{ __('neuronai-studio::connectors.create_plugin_github') }}</button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($view === 'catalog')
        @livewire('neuronai-studio.connectors.catalog')
    @else
        @livewire('neuronai-studio.connectors.manage')
    @endif

    @livewire('neuronai-studio.connectors.detail')
    @livewire('neuronai-studio.connectors.credentials')

    @if ($createModal !== null && $createModalParams !== [])
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:click.self="closeCreateModal">
            <div class="flex max-h-[90vh] w-full {{ $createModalParams['maxWidthClass'] ?? 'max-w-2xl' }} flex-col overflow-hidden rounded-xl border border-border bg-background shadow-xl" @click.stop>
                <div class="flex shrink-0 items-center justify-between border-b border-border px-6 py-4">
                    <h2 class="text-lg font-semibold">{{ $createModalParams['title'] ?? '' }}</h2>
                    <button type="button" class="text-muted-foreground hover:text-foreground" wire:click="closeCreateModal">×</button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                    @livewire($createModalParams['component'], $createModalParams['params'] ?? [], key('create-modal-'.$createModal.'-'.($editEntityId ?? 'new')))
                </div>
            </div>
        </div>
    @endif

    @if ($createModal === 'plugin-upload')
        <x-neuronai-studio::ui.modal maxWidth="md" closeAction="closeCreateModal">
            <x-slot:header>
                <h2 class="text-lg font-semibold">{{ __('neuronai-studio::connectors.create_plugin_upload') }}</h2>
            </x-slot:header>
            <input type="file" wire:model="uploadFile" accept=".zip" />
            @if ($importError)
                <p class="mt-2 text-sm text-destructive">{{ $importError }}</p>
            @endif
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-neuronai-studio::ui.button variant="outline" wire:click="closeCreateModal">{{ __('neuronai-studio::ui.actions.cancel') }}</x-neuronai-studio::ui.button>
                    <x-neuronai-studio::ui.button wire:click="importUpload">{{ __('neuronai-studio::plugins.upload') }}</x-neuronai-studio::ui.button>
                </div>
            </x-slot:footer>
        </x-neuronai-studio::ui.modal>
    @endif

    @if ($createModal === 'plugin-github')
        <x-neuronai-studio::ui.modal maxWidth="md" closeAction="closeCreateModal">
            <x-slot:header>
                <h2 class="text-lg font-semibold">{{ __('neuronai-studio::connectors.create_plugin_github') }}</h2>
            </x-slot:header>
            <x-neuronai-studio::ui.input wire:model="githubUrl" placeholder="https://github.com/owner/repo/tree/main/path/to/plugin" />
            @if ($importError)
                <p class="mt-2 text-sm text-destructive">{{ $importError }}</p>
            @endif
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-neuronai-studio::ui.button variant="outline" wire:click="closeCreateModal">{{ __('neuronai-studio::ui.actions.cancel') }}</x-neuronai-studio::ui.button>
                    <x-neuronai-studio::ui.button wire:click="importGithub">{{ __('neuronai-studio::plugins.import') }}</x-neuronai-studio::ui.button>
                </div>
            </x-slot:footer>
        </x-neuronai-studio::ui.modal>
    @endif
</x-neuronai-studio::ui.page>
