<div class="studio-product-root flex min-h-0 flex-1 flex-col">
    <div class="shrink-0 space-y-3 border-b border-border px-4 py-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1 space-y-2">
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($skill->categoryList() as $catKey)
                        <span class="rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium">{{ $categories[$catKey] ?? $catKey }}</span>
                    @endforeach
                    @if ($skill->source)
                        <span class="rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium uppercase">{{ $skill->source }}</span>
                    @endif
                </div>
                <h1 class="text-xl font-semibold tracking-tight">{{ $skill->displayName() }}</h1>
                <div class="flex flex-wrap gap-3 text-xs text-muted-foreground">
                    <span><code>{{ $skill->slug }}</code></span>
                    @if ($skill->source_url)
                        <a href="{{ $skill->source_url }}" target="_blank" rel="noopener" class="underline hover:text-foreground">{{ __('neuronai-studio::skills.view_source') }}</a>
                    @endif
                    @if (! empty($skill->source_meta['stars']))
                        <span>★ {{ number_format((int) $skill->source_meta['stars']) }}</span>
                    @endif
                    <span>{{ __('neuronai-studio::skills.updated') }} {{ $skill->updated_at?->diffForHumans() }}</span>
                </div>
                <p class="line-clamp-2 max-w-3xl text-sm text-muted-foreground">{{ $skill->description }}</p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <x-neuronai-studio::ui.button size="sm" variant="outline" :href="route('neuronai-studio.skills.edit', $skill)">{{ __('neuronai-studio::ui.actions.edit') }}</x-neuronai-studio::ui.button>
                <x-neuronai-studio::ui.button size="sm" variant="ghost" class="text-destructive" wire:click="deleteSkill" wire:confirm="{{ __('neuronai-studio::ui.confirm.delete_skill') }}">{{ __('neuronai-studio::ui.actions.delete') }}</x-neuronai-studio::ui.button>
                <x-neuronai-studio::ui.button size="sm" variant="outline" :href="route('neuronai-studio.skills.index')">{{ __('neuronai-studio::ui.actions.cancel') }}</x-neuronai-studio::ui.button>
            </div>
        </div>
    </div>

    {{-- Fixed-height split: only the right pane scrolls --}}
    <div class="flex min-h-0 flex-1 overflow-hidden">
        <aside class="flex w-64 shrink-0 flex-col border-r border-border bg-muted/20 md:w-72">
            <div class="shrink-0 border-b border-border px-3 py-2">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">{{ __('neuronai-studio::skills.files') }}</p>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto p-2">
                <button
                    type="button"
                    wire:click="selectFile('SKILL.md')"
                    class="mb-1 w-full rounded-md px-2 py-1.5 text-left text-xs {{ $selectedPath === 'SKILL.md' ? 'bg-accent font-medium text-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground' }}"
                >SKILL.md</button>
                @include('neuronai-studio::livewire.skills.partials.file-tree', ['nodes' => $fileTree, 'selectedPath' => $selectedPath, 'depth' => 0])
            </div>
        </aside>

        <section class="flex min-h-0 min-w-0 flex-1 flex-col bg-background">
            <div class="shrink-0 border-b border-border px-4 py-2">
                <h2 class="truncate font-mono text-sm font-medium">{{ $viewer['title'] }}</h2>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto p-5">
                @if ($viewer['type'] === 'skill_md')
                    <div class="mb-5 overflow-hidden rounded-lg border border-border bg-muted/40">
                        <div class="flex items-center justify-between border-b border-border px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                            <span>YAML</span>
                        </div>
                        <pre class="overflow-x-auto p-3 text-xs leading-relaxed">{{ $viewer['yaml'] }}</pre>
                    </div>
                    <div class="skill-md">
                        {!! \Illuminate\Support\Str::markdown($viewer['content'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                    </div>
                @elseif ($viewer['type'] === 'markdown')
                    <div class="skill-md">
                        {!! \Illuminate\Support\Str::markdown($viewer['content'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                    </div>
                @elseif ($viewer['type'] === 'binary')
                    <p class="text-sm text-muted-foreground">{{ $viewer['content'] }}</p>
                @elseif ($viewer['type'] === 'missing')
                    <p class="text-sm text-destructive">{{ __('neuronai-studio::skills.file_missing') }}</p>
                @else
                    <pre class="overflow-x-auto rounded-lg border border-border bg-muted/40 p-3 font-mono text-xs leading-relaxed">{{ $viewer['content'] }}</pre>
                @endif
            </div>
        </section>
    </div>
</div>
