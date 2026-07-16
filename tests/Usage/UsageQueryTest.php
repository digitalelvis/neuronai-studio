<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Usage;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use DigitalElvis\NeuronAIStudio\Usage\UsageQuery;
use Illuminate\Support\Str;

class UsageQueryTest extends TestCase
{
    public function test_empty_window_returns_zero_totals(): void
    {
        $totals = (new UsageQuery)->aggregate(now()->subDays(30), now());

        $this->assertSame(0, $totals['prompt_tokens']);
        $this->assertSame(0, $totals['completion_tokens']);
        $this->assertSame(0, $totals['total_tokens']);
        $this->assertSame('0.000000', $totals['estimated_cost']);
        $this->assertSame('USD', $totals['currency']);
        $this->assertSame(0, $totals['run_count']);
    }

    public function test_aggregate_sums_top_level_runs_in_window_and_excludes_children(): void
    {
        $thread = StudioThread::create(['id' => (string) Str::uuid()]);
        $parent = $this->createRun($thread->id, null, 10, 5, '0.001250', now()->subDay());

        $this->createRun($thread->id, $parent->id, 100, 50, '1.000000', now()->subDay());
        $this->createRun($thread->id, null, 20, 10, '0.002500', now()->subDays(31));

        $totals = (new UsageQuery)->aggregate(now()->subDays(30), now());

        $this->assertSame(10, $totals['prompt_tokens']);
        $this->assertSame(5, $totals['completion_tokens']);
        $this->assertSame(15, $totals['total_tokens']);
        $this->assertSame('0.001250', $totals['estimated_cost']);
        $this->assertSame(1, $totals['run_count']);
    }

    protected function createRun(
        string $threadId,
        ?string $parentRunId,
        int $promptTokens,
        int $completionTokens,
        string $estimatedCost,
        $startedAt,
    ): StudioRun {
        return StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $threadId,
            'parent_run_id' => $parentRunId,
            'status' => 'completed',
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $promptTokens + $completionTokens,
            'estimated_cost' => $estimatedCost,
            'started_at' => $startedAt,
            'finished_at' => $startedAt,
        ]);
    }
}
