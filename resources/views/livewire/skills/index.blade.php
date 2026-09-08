<x-neuronai-studio::ui.page>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('neuronai-studio::ui.breadcrumbs.skills') }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">{{ __('neuronai-studio::skills.gallery_subtitle') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-neuronai-studio::ui.button
                type="button"
                variant="{{ $ownedOnly ? 'default' : 'outline' }}"
                size="sm"
                wire:click="$toggle('ownedOnly')"
            >{{ __('neuronai-studio::skills.my_skills') }}</x-neuronai-studio::ui.button>

            <div class="relative" x-data="{ open: @entangle('showCreateMenu') }">
                <x-neuronai-studio::ui.button type="button" size="sm" @click="open = !open">
                    {{ __('neuronai-studio::skills.create_own') }} ▾
                </x-neuronai-studio::ui.button>
                <div
                    x-show="open"
                    x-cloak
                    @click.outside="open = false"
                    class="absolute right-0 z-20 mt-2 w-56 rounded-md border border-border bg-background p-1 shadow-lg"
                >
                    <a href="{{ route('neuronai-studio.skills.create') }}" class="block rounded-sm px-3 py-2 text-sm hover:bg-accent">{{ __('neuronai-studio::ui.actions.new_skill') }}</a>
                    <button type="button" class="block w-full rounded-sm px-3 py-2 text-left text-sm hover:bg-accent" wire:click="openUploadModal">{{ __('neuronai-studio::skills.upload_skill') }}</button>
                    <button type="button" class="block w-full rounded-sm px-3 py-2 text-left text-sm hover:bg-accent" wire:click="openGithubModal">{{ __('neuronai-studio::skills.import_github') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-4">
        <x-neuronai-studio::ui.input type="text" wire:model.live.debounce.300ms="filter" placeholder="{{ __('neuronai-studio::skills.search_placeholder') }}" />
    </div>

    <div class="mb-6 flex flex-wrap gap-2">
        <button
            type="button"
            wire:click="$set('category', '')"
            class="inline-flex h-8 items-center rounded-full px-3 text-xs font-medium {{ $category === '' ? 'bg-primary text-primary-foreground' : 'border border-input bg-background hover:bg-accent' }}"
        >{{ __('neuronai-studio::skills.category_all') }}</button>
        @foreach ($categories as $key => $label)
            <button
                type="button"
                wire:click="$set('category', '{{ $key }}')"
                class="inline-flex h-8 items-center rounded-full px-3 text-xs font-medium {{ $category === $key ? 'bg-primary text-primary-foreground' : 'border border-input bg-background hover:bg-accent' }}"
            >{{ __('neuronai-studio::skills.categories.'.$key) !== 'neuronai-studio::skills.categories.'.$key ? __('neuronai-studio::skills.categories.'.$key) : $label }}</button>
        @endforeach
    </div>

    @if ($skills->isEmpty())
        <x-neuronai-studio::ui.card>
            <x-neuronai-studio::ui.empty-state :title="__('neuronai-studio::ui.empty.skills_title')" />
        </x-neuronai-studio::ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($skills as $skill)
                <a
                    href="{{ route('neuronai-studio.skills.show', $skill) }}"
                    class="group overflow-hidden rounded-xl border border-border bg-card transition hover:border-primary/40 hover:shadow-md"
                    wire:key="skill-card-{{ $skill->id }}"
                >
                    <div class="aspect-[16/10] bg-muted">
                        @if ($skill->coverUrl())
                            <img src="{{ $skill->coverUrl() }}" alt="" class="h-full w-full object-cover" />
                        @else
                            <div class="flex h-full items-center justify-center bg-gradient-to-br from-violet-500/20 via-background to-cyan-500/20">
                                <span class="text-3xl font-semibold text-muted-foreground/40">{{ strtoupper(substr($skill->displayName(), 0, 1)) }}</span>
                            </div>
                        @endif
                    </div>
                    <div class="space-y-2 p-4">
                        <h3 class="font-semibold leading-snug group-hover:text-primary">{{ $skill->displayName() }}</h3>
                        <p class="line-clamp-3 text-sm text-muted-foreground">{{ $skill->description }}</p>
                        <div class="flex flex-wrap gap-1.5 pt-1">
                            @if ($skill->source)
                                <span class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">{{ $skill->source }}</span>
                            @endif
                            @foreach ($skill->categoryList() as $catKey)
                                <span class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground">{{ $categories[$catKey] ?? $catKey }}</span>
                            @endforeach
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    @if ($showUploadModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="closeModals">
            <div class="w-full max-w-lg rounded-lg border border-border bg-background p-6 shadow-lg" role="dialog" aria-modal="true">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <h2 class="text-lg font-semibold">{{ __('neuronai-studio::skills.upload_title') }}</h2>
                    <button type="button" class="text-muted-foreground hover:text-foreground" wire:click="closeModals">×</button>
                </div>

                <label class="mb-4 flex cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-border bg-muted/30 px-6 py-10 text-center hover:bg-muted/50">
                    <span class="text-sm font-medium">{{ __('neuronai-studio::skills.upload_drop') }}</span>
                    <span class="mt-1 text-xs text-muted-foreground">.zip / .skill</span>
                    <input type="file" class="hidden" wire:model="uploadFile" accept=".zip,.skill,application/zip" />
                </label>
                @if ($uploadFile)
                    <p class="mb-3 text-xs text-muted-foreground">{{ $uploadFile->getClientOriginalName() }}</p>
                @endif
                @error('uploadFile') <p class="mb-2 text-sm text-destructive">{{ $message }}</p> @enderror

                <div class="mb-4 space-y-1 text-sm text-muted-foreground">
                    <p class="font-medium text-foreground">{{ __('neuronai-studio::skills.upload_requirements') }}</p>
                    <ul class="list-disc space-y-1 pl-5">
                        <li>{{ __('neuronai-studio::skills.upload_req_zip') }}</li>
                        <li>{{ __('neuronai-studio::skills.upload_req_frontmatter') }}</li>
                    </ul>
                </div>

                <label class="mb-4 flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="overwriteOnImport" />
                    {{ __('neuronai-studio::skills.overwrite') }}
                </label>

                @if ($importError !== '')
                    <p class="mb-3 text-sm text-destructive">{{ $importError }}</p>
                @endif

                <div class="flex justify-end gap-2">
                    <x-neuronai-studio::ui.button type="button" variant="outline" wire:click="closeModals">{{ __('neuronai-studio::ui.actions.cancel') }}</x-neuronai-studio::ui.button>
                    <x-neuronai-studio::ui.button type="button" wire:click="importUpload" wire:loading.attr="disabled">{{ __('neuronai-studio::skills.upload_action') }}</x-neuronai-studio::ui.button>
                </div>
            </div>
        </div>
    @endif

    @if ($showGithubModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="closeModals">
            <div class="w-full max-w-lg rounded-lg border border-border bg-background p-6 shadow-lg" role="dialog" aria-modal="true">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <h2 class="text-lg font-semibold">{{ __('neuronai-studio::skills.github_title') }}</h2>
                    <button type="button" class="text-muted-foreground hover:text-foreground" wire:click="closeModals">×</button>
                </div>
                <p class="mb-4 text-sm text-muted-foreground">{{ __('neuronai-studio::skills.github_hint') }}</p>

                <x-neuronai-studio::ui.form-group>
                    <x-neuronai-studio::ui.label>URL</x-neuronai-studio::ui.label>
                    <x-neuronai-studio::ui.input type="url" wire:model="githubUrl" placeholder="https://github.com/username/repo" />
                    @error('githubUrl') <p class="text-sm text-destructive">{{ $message }}</p> @enderror
                </x-neuronai-studio::ui.form-group>

                <label class="mb-4 mt-3 flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="overwriteOnImport" />
                    {{ __('neuronai-studio::skills.overwrite') }}
                </label>

                @if ($importError !== '')
                    <p class="mb-3 text-sm text-destructive">{{ $importError }}</p>
                @endif

                <div class="flex justify-end gap-2">
                    <x-neuronai-studio::ui.button type="button" variant="outline" wire:click="closeModals">{{ __('neuronai-studio::ui.actions.cancel') }}</x-neuronai-studio::ui.button>
                    <x-neuronai-studio::ui.button type="button" wire:click="importGithub" wire:loading.attr="disabled">{{ __('neuronai-studio::skills.github_action') }}</x-neuronai-studio::ui.button>
                </div>
            </div>
        </div>
    @endif
</x-neuronai-studio::ui.page>
