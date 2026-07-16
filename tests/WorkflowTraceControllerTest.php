<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Http\Middleware\EnsureNeuronAIStudioAuthorized;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioTrace;
use DigitalElvis\NeuronAIStudio\Models\StudioTraceSpan;
use Illuminate\Support\Str;

class WorkflowTraceControllerTest extends TestCase
{
    public function test_index_returns_traces_for_workflow(): void
    {
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $workflow = WorkflowDefinition::create([
            'name' => 'Trace API Flow',
            'slug' => 'trace-api-flow',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $thread = StudioThread::create([
            'id' => (string) Str::uuid(),
            'entity_type' => WorkflowDefinition::class,
            'entity_id' => $workflow->id,
        ]);

        $run = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'status' => 'completed',
            'input' => ['message' => 'hello'],
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
            'total_tokens' => 15,
            'estimated_cost' => '0.001250',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $response = $this->getJson(route('neuronai-studio.workflows.traces.index', $workflow));

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $run->id);
        $response->assertJsonPath('data.0.status', 'completed');
        $response->assertJsonPath('data.0.total_tokens', 15);
        $response->assertJsonPath('data.0.estimated_cost', '0.001250');
        $response->assertJsonPath('data.0.currency', 'USD');
    }

    public function test_show_returns_trace_with_steps(): void
    {
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $workflow = WorkflowDefinition::create([
            'name' => 'Trace Detail Flow',
            'slug' => 'trace-detail-flow',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $thread = StudioThread::create([
            'id' => (string) Str::uuid(),
            'entity_type' => WorkflowDefinition::class,
            'entity_id' => $workflow->id,
        ]);

        $run = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'status' => 'completed',
            'input' => ['message' => 'hello'],
            'output' => ['result' => 'ok'],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $trace = StudioTrace::create([
            'id' => (string) Str::uuid(),
            'run_id' => $run->id,
        ]);

        StudioTraceSpan::create([
            'id' => (string) Str::uuid(),
            'trace_id' => $trace->id,
            'name' => 'llm_inference',
            'type' => 'llm',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'status' => 'completed',
            'output' => ['state_snapshot' => ['input' => 'hello']],
            'prompt_tokens' => 7,
            'completion_tokens' => 3,
            'total_tokens' => 10,
            'estimated_cost' => '0.000150',
            'duration_ms' => 5,
        ]);

        $response = $this->getJson(route('neuronai-studio.workflows.runs.show.json', $run));

        $response->assertOk();
        $response->assertJsonPath('trace.id', $run->id);
        $response->assertJsonPath('steps.0.node_id', 'llm_inference');
        $response->assertJsonPath('steps.0.provider', 'openai');
        $response->assertJsonPath('steps.0.model', 'gpt-4o-mini');
        $response->assertJsonPath('steps.0.estimated_cost', '0.000150');
        $response->assertJsonPath('trace.currency', 'USD');
    }

    public function test_traces_show_json_resolves_run_binding_and_returns_populated_trace(): void
    {
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $workflow = WorkflowDefinition::create([
            'name' => 'Legacy Trace Json Flow',
            'slug' => 'legacy-trace-json-flow',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $thread = StudioThread::create([
            'id' => (string) Str::uuid(),
            'entity_type' => WorkflowDefinition::class,
            'entity_id' => $workflow->id,
        ]);

        $run = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'status' => 'completed',
            'input' => ['message' => 'hello'],
            'output' => ['result' => 'ok'],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $trace = StudioTrace::create([
            'id' => (string) Str::uuid(),
            'run_id' => $run->id,
        ]);

        StudioTraceSpan::create([
            'id' => (string) Str::uuid(),
            'trace_id' => $trace->id,
            'name' => 'start_1',
            'type' => 'start',
            'status' => 'completed',
            'output' => ['state_snapshot' => ['input' => 'hello']],
            'duration_ms' => 5,
        ]);

        $response = $this->getJson(route('neuronai-studio.workflows.traces.show.json', $run));

        $response->assertOk();
        $response->assertJsonPath('trace.id', $run->id);
        $response->assertJsonPath('steps.0.node_id', 'start_1');
    }

    public function test_traces_show_redirects_to_runs_show(): void
    {
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $workflow = WorkflowDefinition::create([
            'name' => 'Legacy Trace Redirect Flow',
            'slug' => 'legacy-trace-redirect-flow',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $thread = StudioThread::create([
            'id' => (string) Str::uuid(),
            'entity_type' => WorkflowDefinition::class,
            'entity_id' => $workflow->id,
        ]);

        $run = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'status' => 'completed',
            'input' => ['message' => 'hello'],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $response = $this->get(route('neuronai-studio.workflows.traces.show', $run));

        $response->assertRedirect(route('neuronai-studio.workflows.runs.show', $run));
        $this->assertSame(301, $response->getStatusCode());
    }

    public function test_show_exposes_queued_running_and_awaiting_node_id(): void
    {
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $workflow = WorkflowDefinition::create([
            'name' => 'Polling Flow',
            'slug' => 'polling-flow',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $thread = StudioThread::create([
            'id' => (string) Str::uuid(),
            'entity_type' => WorkflowDefinition::class,
            'entity_id' => $workflow->id,
        ]);

        $queued = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'status' => 'queued',
            'input' => ['message' => 'pending'],
            'started_at' => null,
        ]);

        $running = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'status' => 'running',
            'input' => ['message' => 'in progress'],
            'started_at' => now(),
        ]);

        $awaiting = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'status' => 'awaiting_input',
            'input' => ['message' => 'need human'],
            'checkpoint_state' => ['node_id' => 'human_1'],
            'started_at' => now(),
        ]);

        $this->getJson(route('neuronai-studio.workflows.runs.show.json', $queued))
            ->assertOk()
            ->assertJsonPath('trace.status', 'queued')
            ->assertJsonPath('trace.awaiting_node_id', null);

        $this->getJson(route('neuronai-studio.workflows.runs.show.json', $running))
            ->assertOk()
            ->assertJsonPath('trace.status', 'running');

        $this->getJson(route('neuronai-studio.workflows.runs.show.json', $awaiting))
            ->assertOk()
            ->assertJsonPath('trace.status', 'awaiting_input')
            ->assertJsonPath('trace.awaiting_node_id', 'human_1');
    }
}
