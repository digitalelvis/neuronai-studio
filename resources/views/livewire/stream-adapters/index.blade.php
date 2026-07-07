<x-neuronai-studio::ui.page>
    <x-neuronai-studio::ui.card class="mb-6">
        <x-neuronai-studio::ui.card-content class="pt-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold">External Integration Status</h2>
                    <p class="text-sm text-muted-foreground">
                        Stream agents and workflows to external clients using wire-protocol adapters.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    @if ($enabled)
                        <x-neuronai-studio::ui.badge variant="secondary" class="bg-green-500/10 text-green-500 border-green-500/20">
                            Enabled
                        </x-neuronai-studio::ui.badge>
                    @else
                        <x-neuronai-studio::ui.badge variant="destructive">
                            Disabled
                        </x-neuronai-studio::ui.badge>
                    @endif
                </div>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-3 text-xs border-t border-border pt-4">
                <div>
                    <span class="text-muted-foreground block">Route Prefix</span>
                    <code class="font-mono font-medium text-foreground">{{ $routePrefix }}</code>
                </div>
                <div>
                    <span class="text-muted-foreground block">Middleware</span>
                    <code class="font-mono font-medium text-foreground">{{ implode(', ', $middleware) }}</code>
                </div>
                <div>
                    <span class="text-muted-foreground block">Isolation</span>
                    <span class="font-medium text-foreground">Separate from Studio UI</span>
                </div>
            </div>
        </x-neuronai-studio::ui.card-content>
    </x-neuronai-studio::ui.card>

    <div class="mb-4">
        <h3 class="text-sm font-medium uppercase tracking-wider text-muted-foreground mb-3">Available Protocols</h3>
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($available as $protocol => $adapter)
                <x-neuronai-studio::ui.card>
                    <x-neuronai-studio::ui.card-content class="pt-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <h4 class="font-semibold text-base">{{ $adapter['label'] }}</h4>
                                <p class="text-xs text-muted-foreground mt-0.5">{{ $adapter['framework'] }}</p>
                            </div>
                            <x-neuronai-studio::ui.badge variant="outline" class="font-mono text-[10px]">
                                {{ $protocol }}
                            </x-neuronai-studio::ui.badge>
                        </div>
                        <div class="mt-4 space-y-2 text-xs">
                            <div>
                                <span class="text-muted-foreground">Headers:</span>
                                <code class="ml-1 text-muted-foreground">{{ json_encode($adapter['headers']) }}</code>
                            </div>
                            @if (!empty($adapter['docs']))
                                <div>
                                    <a href="{{ $adapter['docs'] }}" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline inline-flex items-center gap-1">
                                        Protocol Specification →
                                    </a>
                                </div>
                            @endif
                        </div>
                    </x-neuronai-studio::ui.card-content>
                </x-neuronai-studio::ui.card>
            @endforeach
        </div>
    </div>

    <div>
        <h3 class="text-sm font-medium uppercase tracking-wider text-muted-foreground mb-3">Roadmap Protocols</h3>
        <div class="grid gap-4 md:grid-cols-3">
            @foreach ($roadmap as $protocol => $adapter)
                <x-neuronai-studio::ui.card class="opacity-75">
                    <x-neuronai-studio::ui.card-content class="pt-4 pb-4">
                        <div class="flex items-center justify-between">
                            <h4 class="font-medium text-sm">{{ $adapter['label'] }}</h4>
                            <x-neuronai-studio::ui.badge variant="outline" class="text-[10px]">Roadmap</x-neuronai-studio::ui.badge>
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">{{ $adapter['framework'] }}</p>
                    </x-neuronai-studio::ui.card-content>
                </x-neuronai-studio::ui.card>
            @endforeach
        </div>
    </div>
</x-neuronai-studio::ui.page>
