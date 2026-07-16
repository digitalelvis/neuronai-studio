<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Usage;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\StudioTrace;
use DigitalElvis\NeuronAIStudio\Models\StudioTraceSpan;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Str;

class UsageExportApiTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('neuronai-studio.usage.export.enabled', true);
        $app['config']->set('neuronai-studio.stream_adapters.middleware', [SubstituteBindings::class]);
        $app['config']->set('neuronai-studio.usage.export.middleware', [SubstituteBindings::class]);
    }

    public function test_aggregate_returns_zero_totals_for_empty_window(): void
    {
        $response = $this->getJson(route('neuronai-studio.usage.aggregate', [
            'from' => now()->subDay()->toDateString(),
            'to' => now()->toDateString(),
        ]));

        $response->assertOk()
            ->assertJsonPath('totals.prompt_tokens', 0)
            ->assertJsonPath('totals.run_count', 0)
            ->assertJsonPath('breakdown', []);
    }

    public function test_aggregate_excludes_child_runs_and_supports_group_by_model(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Export WF',
            'slug' => 'export-wf-'.uniqid(),
        ]);
        $thread = StudioThread::create([
            'id' => (string) Str::uuid(),
            'entity_type' => WorkflowDefinition::class,
            'entity_id' => $workflow->id,
        ]);
        $parent = $this->createRun($thread->id, null, 10, 5, '0.001000', now()->subHour());
        $this->createRun($thread->id, $parent->id, 100, 50, '1.000000', now()->subHour());
        $this->createLlmSpan($parent, 'openai', 'gpt-4o-mini', 10, 5, '0.001000');

        $response = $this->getJson(route('neuronai-studio.usage.aggregate', [
            'from' => now()->subDay()->toDateString(),
            'to' => now()->toDateString(),
            'group_by' => 'model',
            'entity_type' => 'workflow',
            'entity_id' => $workflow->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('totals.prompt_tokens', 10)
            ->assertJsonPath('totals.run_count', 1)
            ->assertJsonPath('breakdown.0.model', 'gpt-4o-mini');
    }

    public function test_aggregate_validates_from_after_to(): void
    {
        $this->getJson(route('neuronai-studio.usage.aggregate', [
            'from' => now()->toDateString(),
            'to' => now()->subDay()->toDateString(),
        ]))->assertStatus(422);
    }

    public function test_show_run_returns_detail_and_404_when_missing(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Export Agent',
            'slug' => 'export-agent-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'test',
        ]);
        $thread = StudioThread::create([
            'id' => (string) Str::uuid(),
            'entity_type' => AgentDefinition::class,
            'entity_id' => $agent->id,
        ]);
        $run = $this->createRun($thread->id, null, 3, 1, '0.000100', now()->subMinute());
        $this->createLlmSpan($run, 'openai', 'gpt-4o-mini', 3, 1, '0.000100');

        $this->getJson(route('neuronai-studio.usage.runs.show', ['run' => $run->id]))
            ->assertOk()
            ->assertJsonPath('id', $run->id)
            ->assertJsonPath('entity.type', 'agent')
            ->assertJsonPath('spans.0.model', 'gpt-4o-mini');

        $this->getJson(route('neuronai-studio.usage.runs.show', ['run' => (string) Str::uuid()]))
            ->assertNotFound();
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
