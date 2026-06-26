<x-neuronai-studio::ui.page>
    <x-neuronai-studio::ui.card class="mb-4">
        <x-neuronai-studio::ui.card-content class="grid gap-4 pt-4 md:grid-cols-4">
            <div>
                <div class="text-sm text-muted-foreground">Status</div>
                <div class="font-medium">{{ $run->status }}</div>
            </div>
            <div>
                <div class="text-sm text-muted-foreground">Passed</div>
                <div class="font-medium">{{ $run->passed_count }} / {{ $run->passed_count + $run->failed_count }}</div>
            </div>
            <div>
                <div class="text-sm text-muted-foreground">Success Rate</div>
                <div class="font-medium">{{ number_format($run->success_rate * 100, 1) }}%</div>
            </div>
            <div>
                <div class="text-sm text-muted-foreground">Duration</div>
                <div class="font-medium">{{ $run->total_time_ms }}ms</div>
            </div>
        </x-neuronai-studio::ui.card-content>
    </x-neuronai-studio::ui.card>

    <x-neuronai-studio::ui.card>
        <x-neuronai-studio::ui.table>
            <x-neuronai-studio::ui.table-head>
                <tr>
                    <x-neuronai-studio::ui.table-header>#</x-neuronai-studio::ui.table-header>
                    <x-neuronai-studio::ui.table-header>Input</x-neuronai-studio::ui.table-header>
                    <x-neuronai-studio::ui.table-header>Result</x-neuronai-studio::ui.table-header>
                    <x-neuronai-studio::ui.table-header>Output</x-neuronai-studio::ui.table-header>
                    <x-neuronai-studio::ui.table-header>Time</x-neuronai-studio::ui.table-header>
                </tr>
            </x-neuronai-studio::ui.table-head>
            <x-neuronai-studio::ui.table-body>
                @foreach ($run->items as $item)
                    <x-neuronai-studio::ui.table-row wire:key="eval-run-item-{{ $item->id }}">
                        <x-neuronai-studio::ui.table-cell>{{ $item->case_index + 1 }}</x-neuronai-studio::ui.table-cell>
                        <x-neuronai-studio::ui.table-cell>
                            <code class="text-xs">{{ \Illuminate\Support\Str::limit(is_string($item->input['input'] ?? null) ? $item->input['input'] : json_encode($item->input), 80) }}</code>
                        </x-neuronai-studio::ui.table-cell>
                        <x-neuronai-studio::ui.table-cell>
                            <x-neuronai-studio::ui.badge variant="{{ $item->passed ? 'secondary' : 'destructive' }}">
                                {{ $item->passed ? 'PASS' : 'FAIL' }}
                            </x-neuronai-studio::ui.badge>
                            @if ($item->error_message)
                                <div class="mt-1 text-sm text-destructive">{{ $item->error_message }}</div>
                            @endif
                            @if (! $item->passed && ! empty($item->failures))
                                <ul class="mt-2 list-disc pl-4 text-sm text-muted-foreground">
                                    @foreach ($item->failures as $failure)
                                        <li>{{ $failure['description'] ?? $failure['message'] ?? 'Assertion failed' }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </x-neuronai-studio::ui.table-cell>
                        <x-neuronai-studio::ui.table-cell>
                            <div class="max-w-md whitespace-pre-wrap text-sm">{{ \Illuminate\Support\Str::limit((string) $item->output, 200) }}</div>
                        </x-neuronai-studio::ui.table-cell>
                        <x-neuronai-studio::ui.table-cell>{{ $item->execution_time_ms }}ms</x-neuronai-studio::ui.table-cell>
                    </x-neuronai-studio::ui.table-row>
                @endforeach
            </x-neuronai-studio::ui.table-body>
        </x-neuronai-studio::ui.table>
    </x-neuronai-studio::ui.card>
</x-neuronai-studio::ui.page>
