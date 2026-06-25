<x-neuronai-studio::ui.page>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-neuronai-studio::ui.stat-card label="Agents" :value="$agentCount" />
        <x-neuronai-studio::ui.stat-card label="Tools" :value="$toolCount" />
        <x-neuronai-studio::ui.stat-card label="MCP Servers" :value="$mcpServerCount" />
        <x-neuronai-studio::ui.stat-card label="Workflows" :value="$workflowCount" />
    </div>

    <x-neuronai-studio::ui.card>
        <x-neuronai-studio::ui.card-header>
            <h2 class="text-lg font-semibold">Recent Workflow Runs</h2>
        </x-neuronai-studio::ui.card-header>
        <x-neuronai-studio::ui.card-content>
            @if ($recentRuns->isEmpty())
                <x-neuronai-studio::ui.empty-state title="No workflow runs yet" description="Run a workflow from the editor to see activity here." />
            @else
                <x-neuronai-studio::ui.table>
                    <x-neuronai-studio::ui.table-head>
                        <tr>
                            <x-neuronai-studio::ui.table-header>ID</x-neuronai-studio::ui.table-header>
                            <x-neuronai-studio::ui.table-header>Workflow</x-neuronai-studio::ui.table-header>
                            <x-neuronai-studio::ui.table-header>Status</x-neuronai-studio::ui.table-header>
                            <x-neuronai-studio::ui.table-header>Started</x-neuronai-studio::ui.table-header>
                            <x-neuronai-studio::ui.table-header></x-neuronai-studio::ui.table-header>
                        </tr>
                    </x-neuronai-studio::ui.table-head>
                    <x-neuronai-studio::ui.table-body>
                        @foreach ($recentRuns as $run)
                            <x-neuronai-studio::ui.table-row wire:key="run-{{ $run->id }}">
                                <x-neuronai-studio::ui.table-cell>#{{ $run->id }}</x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.table-cell>{{ $run->workflow?->name }}</x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.table-cell>
                                    <x-neuronai-studio::ui.badge :variant="$run->status">{{ $run->status }}</x-neuronai-studio::ui.badge>
                                </x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.table-cell class="text-muted-foreground">{{ $run->started_at?->diffForHumans() }}</x-neuronai-studio::ui.table-cell>
                                <x-neuronai-studio::ui.table-cell>
                                    <x-neuronai-studio::ui.button variant="ghost" size="sm" :href="route('neuronai-studio.workflows.runs.show', $run)">View</x-neuronai-studio::ui.button>
                                </x-neuronai-studio::ui.table-cell>
                            </x-neuronai-studio::ui.table-row>
                        @endforeach
                    </x-neuronai-studio::ui.table-body>
                </x-neuronai-studio::ui.table>
            @endif
        </x-neuronai-studio::ui.card-content>
    </x-neuronai-studio::ui.card>
</x-neuronai-studio::ui.page>
