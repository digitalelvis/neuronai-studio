<?php

namespace DigitalElvis\NeuronAIStudio\Usage;

use Carbon\CarbonInterface;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;

class UsageQuery
{
    /**
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int, estimated_cost: string, currency: string, run_count: int}
     */
    public function aggregate(
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $entityType = null,
        int|string|null $entityId = null,
    ): array {
        $query = StudioRun::query()
            ->whereNull('parent_run_id')
            ->whereBetween('started_at', [$from, $to]);

        if ($entityType !== null) {
            $query->whereHas('thread', function ($query) use ($entityType, $entityId): void {
                $query->where('entity_type', $entityType);

                if ($entityId !== null) {
                    $query->where('entity_id', $entityId);
                }
            });
        }

        $totals = $query->toBase()
            ->selectRaw('COALESCE(SUM(prompt_tokens), 0) as prompt_tokens')
            ->selectRaw('COALESCE(SUM(completion_tokens), 0) as completion_tokens')
            ->selectRaw('COALESCE(SUM(total_tokens), 0) as total_tokens')
            ->selectRaw('COALESCE(SUM(estimated_cost), 0) as estimated_cost')
            ->selectRaw('COUNT(*) as run_count')
            ->first();

        return [
            'prompt_tokens' => (int) ($totals->prompt_tokens ?? 0),
            'completion_tokens' => (int) ($totals->completion_tokens ?? 0),
            'total_tokens' => (int) ($totals->total_tokens ?? 0),
            'estimated_cost' => number_format((float) ($totals->estimated_cost ?? 0), 6, '.', ''),
            'currency' => (string) config('neuronai-studio.usage.currency', 'USD'),
            'run_count' => (int) ($totals->run_count ?? 0),
        ];
    }
}
