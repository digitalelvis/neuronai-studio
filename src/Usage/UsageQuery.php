<?php

namespace DigitalElvis\NeuronAIStudio\Usage;

use Carbon\CarbonInterface;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\StudioTrace;
use DigitalElvis\NeuronAIStudio\Models\StudioTraceSpan;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use Illuminate\Database\Eloquent\Builder;

class UsageQuery
{
    /**
     * Aggregate top-level run usage for a window (children excluded from totals).
     *
     * Flat keys stay stable for Dashboard. `breakdown` is empty unless `$groupBy`
     * is `model` or `entity`. When `$model` is set, totals come from matching
     * llm spans in the window (span `started_at`), not run rollups.
     *
     * @return array{
     *     prompt_tokens: int,
     *     completion_tokens: int,
     *     total_tokens: int,
     *     estimated_cost: string,
     *     currency: string,
     *     run_count: int,
     *     breakdown: list<array<string, mixed>>
     * }
     */
    public function aggregate(
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $entityType = null,
        int|string|null $entityId = null,
        ?string $groupBy = null,
        ?string $model = null,
    ): array {
        $entityFqcn = $this->resolveEntityType($entityType);
        $currency = (string) config('neuronai-studio.usage.currency', 'USD');
        $modelFilter = ($model !== null && $model !== '') ? $model : null;

        $totals = $modelFilter !== null
            ? $this->aggregateFromSpans($from, $to, $entityFqcn, $entityId, $modelFilter)
            : $this->aggregateFromRuns($from, $to, $entityFqcn, $entityId);

        $breakdown = match ($groupBy) {
            'model' => $this->breakdownByModel($from, $to, $entityFqcn, $entityId, $modelFilter),
            'entity' => $this->breakdownByEntity($from, $to, $entityFqcn, $entityId, $modelFilter),
            default => [],
        };

        return [
            ...$totals,
            'currency' => $currency,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function runDetail(string $runId): ?array
    {
        $run = StudioRun::query()
            ->with(['thread.entity', 'traces.spans' => function ($query): void {
                $query->where('type', 'llm')->orderBy('started_at');
            }])
            ->find($runId);

        if ($run === null) {
            return null;
        }

        $entity = $run->thread?->entity;
        $entityType = $this->shortEntityType($run->thread?->entity_type);

        $spans = $run->traces
            ->flatMap(fn ($trace) => $trace->spans)
            ->map(fn (StudioTraceSpan $span) => [
                'id' => $span->id,
                'provider' => $span->provider,
                'model' => $span->model,
                'prompt_tokens' => (int) $span->prompt_tokens,
                'completion_tokens' => (int) $span->completion_tokens,
                'total_tokens' => (int) $span->total_tokens,
                'estimated_cost' => number_format((float) $span->estimated_cost, 6, '.', ''),
            ])
            ->values()
            ->all();

        return [
            'id' => $run->id,
            'thread_id' => $run->thread_id,
            'parent_run_id' => $run->parent_run_id,
            'is_child' => $run->parent_run_id !== null,
            'entity' => [
                'type' => $entityType,
                'id' => $run->thread?->entity_id,
                'name' => $entity?->name ?? null,
            ],
            'status' => $run->status,
            'prompt_tokens' => (int) $run->prompt_tokens,
            'completion_tokens' => (int) $run->completion_tokens,
            'total_tokens' => (int) $run->total_tokens,
            'estimated_cost' => number_format((float) $run->estimated_cost, 6, '.', ''),
            'currency' => (string) config('neuronai-studio.usage.currency', 'USD'),
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'spans' => $spans,
        ];
    }

    /**
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int, estimated_cost: string, run_count: int}
     */
    protected function aggregateFromRuns(
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $entityFqcn,
        int|string|null $entityId,
    ): array {
        $query = StudioRun::query()
            ->whereNull('parent_run_id')
            ->whereBetween('started_at', [$from, $to]);

        $this->applyEntityFilter($query, $entityFqcn, $entityId);

        $totals = $query->toBase()
            ->selectRaw('COALESCE(SUM(prompt_tokens), 0) as prompt_tokens')
            ->selectRaw('COALESCE(SUM(completion_tokens), 0) as completion_tokens')
            ->selectRaw('COALESCE(SUM(total_tokens), 0) as total_tokens')
            ->selectRaw('COALESCE(SUM(estimated_cost), 0) as estimated_cost')
            ->selectRaw('COUNT(*) as run_count')
            ->first();

        return $this->formatTotals($totals);
    }

    /**
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int, estimated_cost: string, run_count: int}
     */
    protected function aggregateFromSpans(
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $entityFqcn,
        int|string|null $entityId,
        string $model,
    ): array {
        $spansTable = (new StudioTraceSpan)->getTable();
        $tracesTable = (new StudioTrace)->getTable();

        $query = $this->llmSpansInWindow($from, $to, $entityFqcn, $entityId)
            ->where("{$spansTable}.model", $model);

        $totals = $query->toBase()
            ->selectRaw("COALESCE(SUM({$spansTable}.prompt_tokens), 0) as prompt_tokens")
            ->selectRaw("COALESCE(SUM({$spansTable}.completion_tokens), 0) as completion_tokens")
            ->selectRaw("COALESCE(SUM({$spansTable}.total_tokens), 0) as total_tokens")
            ->selectRaw("COALESCE(SUM({$spansTable}.estimated_cost), 0) as estimated_cost")
            ->selectRaw("COUNT(DISTINCT {$tracesTable}.run_id) as run_count")
            ->first();

        return $this->formatTotals($totals);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function breakdownByModel(
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $entityFqcn,
        int|string|null $entityId,
        ?string $model,
    ): array {
        $spansTable = (new StudioTraceSpan)->getTable();
        $query = $this->llmSpansInWindow($from, $to, $entityFqcn, $entityId);

        if ($model !== null) {
            $query->where("{$spansTable}.model", $model);
        }

        $rows = $query->toBase()
            ->selectRaw("{$spansTable}.provider as provider")
            ->selectRaw("{$spansTable}.model as model")
            ->selectRaw("COALESCE(SUM({$spansTable}.prompt_tokens), 0) as prompt_tokens")
            ->selectRaw("COALESCE(SUM({$spansTable}.completion_tokens), 0) as completion_tokens")
            ->selectRaw("COALESCE(SUM({$spansTable}.total_tokens), 0) as total_tokens")
            ->selectRaw("COALESCE(SUM({$spansTable}.estimated_cost), 0) as estimated_cost")
            ->groupBy("{$spansTable}.provider", "{$spansTable}.model")
            ->orderBy("{$spansTable}.provider")
            ->orderBy("{$spansTable}.model")
            ->get();

        return $rows->map(fn ($row) => [
            'provider' => $row->provider,
            'model' => $row->model,
            'prompt_tokens' => (int) $row->prompt_tokens,
            'completion_tokens' => (int) $row->completion_tokens,
            'total_tokens' => (int) $row->total_tokens,
            'estimated_cost' => number_format((float) $row->estimated_cost, 6, '.', ''),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function breakdownByEntity(
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $entityFqcn,
        int|string|null $entityId,
        ?string $model,
    ): array {
        if ($model !== null) {
            return $this->breakdownByEntityFromSpans($from, $to, $entityFqcn, $entityId, $model);
        }

        $runsTable = (new StudioRun)->getTable();
        $threadsTable = (new StudioThread)->getTable();

        $query = StudioRun::query()
            ->whereNull("{$runsTable}.parent_run_id")
            ->whereBetween("{$runsTable}.started_at", [$from, $to])
            ->join($threadsTable, "{$threadsTable}.id", '=', "{$runsTable}.thread_id");

        if ($entityFqcn !== null) {
            $query->where("{$threadsTable}.entity_type", $entityFqcn);
            if ($entityId !== null) {
                $query->where("{$threadsTable}.entity_id", $entityId);
            }
        }

        $rows = $query->toBase()
            ->selectRaw("{$threadsTable}.entity_type as entity_type")
            ->selectRaw("{$threadsTable}.entity_id as entity_id")
            ->selectRaw("COALESCE(SUM({$runsTable}.prompt_tokens), 0) as prompt_tokens")
            ->selectRaw("COALESCE(SUM({$runsTable}.completion_tokens), 0) as completion_tokens")
            ->selectRaw("COALESCE(SUM({$runsTable}.total_tokens), 0) as total_tokens")
            ->selectRaw("COALESCE(SUM({$runsTable}.estimated_cost), 0) as estimated_cost")
            ->selectRaw("COUNT({$runsTable}.id) as run_count")
            ->groupBy("{$threadsTable}.entity_type", "{$threadsTable}.entity_id")
            ->get();

        return $rows->map(fn ($row) => [
            'entity_type' => $this->shortEntityType($row->entity_type),
            'entity_id' => $row->entity_id,
            'prompt_tokens' => (int) $row->prompt_tokens,
            'completion_tokens' => (int) $row->completion_tokens,
            'total_tokens' => (int) $row->total_tokens,
            'estimated_cost' => number_format((float) $row->estimated_cost, 6, '.', ''),
            'run_count' => (int) $row->run_count,
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function breakdownByEntityFromSpans(
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $entityFqcn,
        int|string|null $entityId,
        string $model,
    ): array {
        $spansTable = (new StudioTraceSpan)->getTable();
        $threadsTable = (new StudioThread)->getTable();
        $tracesTable = (new StudioTrace)->getTable();

        $query = $this->llmSpansInWindow($from, $to, $entityFqcn, $entityId)
            ->where("{$spansTable}.model", $model);

        $rows = $query->toBase()
            ->selectRaw("{$threadsTable}.entity_type as entity_type")
            ->selectRaw("{$threadsTable}.entity_id as entity_id")
            ->selectRaw("COALESCE(SUM({$spansTable}.prompt_tokens), 0) as prompt_tokens")
            ->selectRaw("COALESCE(SUM({$spansTable}.completion_tokens), 0) as completion_tokens")
            ->selectRaw("COALESCE(SUM({$spansTable}.total_tokens), 0) as total_tokens")
            ->selectRaw("COALESCE(SUM({$spansTable}.estimated_cost), 0) as estimated_cost")
            ->selectRaw("COUNT(DISTINCT {$tracesTable}.run_id) as run_count")
            ->groupBy("{$threadsTable}.entity_type", "{$threadsTable}.entity_id")
            ->get();

        return $rows->map(fn ($row) => [
            'entity_type' => $this->shortEntityType($row->entity_type),
            'entity_id' => $row->entity_id,
            'prompt_tokens' => (int) $row->prompt_tokens,
            'completion_tokens' => (int) $row->completion_tokens,
            'total_tokens' => (int) $row->total_tokens,
            'estimated_cost' => number_format((float) $row->estimated_cost, 6, '.', ''),
            'run_count' => (int) $row->run_count,
        ])->all();
    }

    protected function llmSpansInWindow(
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $entityFqcn,
        int|string|null $entityId,
    ): Builder {
        $spansTable = (new StudioTraceSpan)->getTable();
        $tracesTable = (new StudioTrace)->getTable();
        $runsTable = (new StudioRun)->getTable();
        $threadsTable = (new StudioThread)->getTable();

        $query = StudioTraceSpan::query()
            ->where("{$spansTable}.type", 'llm')
            ->whereBetween("{$spansTable}.started_at", [$from, $to])
            ->join($tracesTable, "{$tracesTable}.id", '=', "{$spansTable}.trace_id")
            ->join($runsTable, "{$runsTable}.id", '=', "{$tracesTable}.run_id")
            ->join($threadsTable, "{$threadsTable}.id", '=', "{$runsTable}.thread_id");

        if ($entityFqcn !== null) {
            $query->where("{$threadsTable}.entity_type", $entityFqcn);
            if ($entityId !== null) {
                $query->where("{$threadsTable}.entity_id", $entityId);
            }
        }

        return $query;
    }

    protected function applyEntityFilter(Builder $query, ?string $entityFqcn, int|string|null $entityId): void
    {
        if ($entityFqcn === null) {
            return;
        }

        $query->whereHas('thread', function ($threadQuery) use ($entityFqcn, $entityId): void {
            $threadQuery->where('entity_type', $entityFqcn);

            if ($entityId !== null) {
                $threadQuery->where('entity_id', $entityId);
            }
        });
    }

    protected function resolveEntityType(?string $entityType): ?string
    {
        if ($entityType === null || $entityType === '') {
            return null;
        }

        return match (strtolower($entityType)) {
            'agent' => AgentDefinition::class,
            'workflow' => WorkflowDefinition::class,
            default => $entityType,
        };
    }

    protected function shortEntityType(?string $fqcn): ?string
    {
        return match ($fqcn) {
            AgentDefinition::class => 'agent',
            WorkflowDefinition::class => 'workflow',
            default => $fqcn,
        };
    }

    /**
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int, estimated_cost: string, run_count: int}
     */
    protected function formatTotals(?object $totals): array
    {
        return [
            'prompt_tokens' => (int) ($totals->prompt_tokens ?? 0),
            'completion_tokens' => (int) ($totals->completion_tokens ?? 0),
            'total_tokens' => (int) ($totals->total_tokens ?? 0),
            'estimated_cost' => number_format((float) ($totals->estimated_cost ?? 0), 6, '.', ''),
            'run_count' => (int) ($totals->run_count ?? 0),
        ];
    }
}
