<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Usage;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\StudioTrace;
use DigitalElvis\NeuronAIStudio\Models\StudioTraceSpan;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
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
        $this->assertSame([], $totals['breakdown']);
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

    public function test_group_by_model_returns_breakdown_from_llm_spans(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Usage WF',
            'slug' => 'usage-wf-'.uniqid(),
        ]);
        $thread = StudioThread::create([
            'id' => (string) Str::uuid(),
            'entity_type' => WorkflowDefinition::class,
            'entity_id' => $workflow->id,
        ]);
        $run = $this->createRun($thread->id, null, 0, 0, '0', now()->subHour());
        $this->createLlmSpan($run, 'openai', 'gpt-4o-mini', 100, 40, '0.001000');
        $this->createLlmSpan($run, 'openai', 'gpt-4o', 50, 10, '0.002000');

        $result = (new UsageQuery)->aggregate(now()->subDay(), now(), groupBy: 'model');

        $this->assertCount(2, $result['breakdown']);
        $this->assertSame('gpt-4o', $result['breakdown'][0]['model']);
        $this->assertSame('gpt-4o-mini', $result['breakdown'][1]['model']);
    }

    public function test_run_detail_includes_llm_spans_entity_and_parent_run_id(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Usage Agent',
            'slug' => 'usage-agent-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'test',
        ]);
        $thread = StudioThread::create([
            'id' => (string) Str::uuid(),
            'entity_type' => AgentDefinition::class,
            'entity_id' => $agent->id,
        ]);
        $parent = $this->createRun($thread->id, null, 10, 5, '0.001000', now()->subHour());
        $child = $this->createRun($thread->id, $parent->id, 8, 2, '0.000500', now()->subHour());
        $this->createLlmSpan($child, 'openai', 'gpt-4o-mini', 8, 2, '0.000500');

        $detail = (new UsageQuery)->runDetail($child->id);

        $this->assertNotNull($detail);
        $this->assertSame($parent->id, $detail['parent_run_id']);
        $this->assertTrue($detail['is_child']);
        $this->assertSame('agent', $detail['entity']['type']);
        $this->assertSame($agent->id, $detail['entity']['id']);
        $this->assertCount(1, $detail['spans']);
        $this->assertSame('gpt-4o-mini', $detail['spans'][0]['model']);
    }

    public function test_run_detail_returns_null_for_missing_run(): void
    {
        $this->assertNull((new UsageQuery)->runDetail((string) Str::uuid()));
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

    protected function createLlmSpan(
        StudioRun $run,
        string $provider,
        string $model,
        int $prompt,
        int $completion,
        string $cost,
    ): StudioTraceSpan {
        $trace = StudioTrace::create([
            'id' => (string) Str::uuid(),
            'run_id' => $run->id,
        ]);

        return StudioTraceSpan::create([
            'id' => (string) Str::uuid(),
            'trace_id' => $trace->id,
            'name' => 'llm_inference',
            'type' => 'llm',
            'provider' => $provider,
            'model' => $model,
            'status' => 'completed',
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $prompt + $completion,
            'estimated_cost' => $cost,
            'started_at' => $run->started_at,
            'finished_at' => $run->started_at,
        ]);
    }
}
