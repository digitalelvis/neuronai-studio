<x-neuronai-studio::ui.page>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-neuronai-studio::ui.stat-card label="Agents" :value="$agentCount" />
        <x-neuronai-studio::ui.stat-card label="Tools" :value="$toolCount" />
        <x-neuronai-studio::ui.stat-card label="MCP Servers" :value="$mcpServerCount" />
        <x-neuronai-studio::ui.stat-card label="Workflows" :value="$workflowCount" />
        <x-neuronai-studio::ui.stat-card label="Tokens ({{ $usageWindowLabel }})" :value="number_format($usageTotals['total_tokens'])" />
        <x-neuronai-studio::ui.stat-card
            label="Est. cost ({{ $usageWindowLabel }})"
            :value="$usageTotals['currency'].' '.number_format((float) $usageTotals['estimated_cost'], 2)"
        />
    </div>

    <x-neuronai-studio::ui.card>
        <x-neuronai-studio::ui.card-header>
            <h2 class="text-lg font-semibold">Recent Workflow Traces</h2>
        </x-neuronai-studio::ui.card-header>
        <x-neuronai-studio::ui.card-content>
            @if ($recentTraces->isEmpty())
                <x-neuronai-studio::ui.empty-state title="No workflow traces yet" description="Test a workflow from the editor to see activity here." />
            @else
                <x-neuronai-studio::ui.table>
                    <x-neuronai-studio::ui.table-head>
                        <tr>
                            <x-neuronai-studio::ui.table-header>ID</x-neuronai-studio::ui.table-header>
                            <x-neuronai-studio::ui.table-header>Workflow</x-neuronai-studio::ui.table-header>
                            <x-neuronai-studio::ui.table-header>Status</x-neuronai-studio::ui.table-header>
                            <x-neuronai-studio::ui.table-header>Tokens</x-neuronai-studio::ui.table-header>
                            <x-neuronai-studio::ui.table-header>Est. cost</x-neuronai-studio::ui.table-header>
                            <x-neuronai-studio::ui.table-header>Started</x-neuronai-studio::ui.table-header>
                            <x-neuronai-studio::ui.table-header></x-neuronai-studio::ui.table-header>
                        </tr>
                    </x-neuronai-studio::ui.table-head>
                    <x-neuronai-studio::ui.table-body>
                        @foreach ($recentTraces as $trace)
                            <x-neuronai-studio::ui.table-row wire:key="trace-{{ $trace->id }}">
                                <x-neuronai-studio::ui.table-cell>#{{ $trace->id }}</x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.table-cell>{{ $trace->workflow?->name }}</x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.table-cell>
                                    <x-neuronai-studio::ui.badge :variant="$trace->status">{{ $trace->status }}</x-neuronai-studio::ui.badge>
                                </x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.table-cell>{{ number_format($trace->total_tokens ?? 0) }}</x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.table-cell>{{ $usageTotals['currency'] }} {{ number_format((float) ($trace->estimated_cost ?? 0), 2) }}</x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.table-cell class="text-muted-foreground">{{ $trace->started_at?->diffForHumans() }}</x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.table-cell>
                                    <x-neuronai-studio::ui.button variant="ghost" size="sm" :href="route('neuronai-studio.workflows.traces.show', $trace)">View</x-neuronai-studio::ui.button>
                                </x-neuronai-studio::ui.table-cell>
                            </x-neuronai-studio::ui.table-row>
                        @endforeach
                    </x-neuronai-studio::ui.table-body>
                </x-neuronai-studio::ui.table>
            @endif
        </x-neuronai-studio::ui.card-content>
    </x-neuronai-studio::ui.card>
</x-neuronai-studio::ui.page>
