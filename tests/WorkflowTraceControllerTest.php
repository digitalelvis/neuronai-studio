<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Http\Middleware\EnsureNeuronAIStudioAuthorized;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Models\WorkflowTrace;
use DigitalElvis\NeuronAIStudio\Models\WorkflowTraceStep;

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

        $trace = WorkflowTrace::create([
            'workflow_definition_id' => $workflow->id,
            'status' => 'completed',
            'input' => ['message' => 'hello'],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $response = $this->getJson(route('neuronai-studio.workflows.traces.index', $workflow));

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $trace->id);
        $response->assertJsonPath('data.0.status', 'completed');
    }

    public function test_show_returns_trace_with_steps(): void
    {
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $workflow = WorkflowDefinition::create([
            'name' => 'Trace Detail Flow',
            'slug' => 'trace-detail-flow',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $trace = WorkflowTrace::create([
            'workflow_definition_id' => $workflow->id,
            'status' => 'completed',
            'input' => ['message' => 'hello'],
            'output' => ['result' => 'ok'],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        WorkflowTraceStep::create([
            'workflow_trace_id' => $trace->id,
            'node_id' => 'start_1',
            'node_type' => 'start',
            'state_snapshot' => ['input' => 'hello'],
            'duration_ms' => 5,
        ]);

        $response = $this->getJson(route('neuronai-studio.workflows.traces.show.json', $trace));

        $response->assertOk();
        $response->assertJsonPath('trace.id', $trace->id);
        $response->assertJsonPath('steps.0.node_id', 'start_1');
    }

    public function test_show_exposes_queued_running_and_awaiting_node_id(): void
    {
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $workflow = WorkflowDefinition::create([
            'name' => 'Polling Flow',
            'slug' => 'polling-flow',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $queued = WorkflowTrace::create([
            'workflow_definition_id' => $workflow->id,
            'status' => 'queued',
            'input' => ['message' => 'pending'],
            'started_at' => null,
        ]);

        $running = WorkflowTrace::create([
            'workflow_definition_id' => $workflow->id,
            'status' => 'running',
            'input' => ['message' => 'in progress'],
            'started_at' => now(),
        ]);

        $awaiting = WorkflowTrace::create([
            'workflow_definition_id' => $workflow->id,
            'status' => 'awaiting_input',
            'input' => ['message' => 'need human'],
            'awaiting_node_id' => 'human_1',
            'started_at' => now(),
        ]);

        $this->getJson(route('neuronai-studio.workflows.traces.show.json', $queued))
            ->assertOk()
            ->assertJsonPath('trace.status', 'queued')
            ->assertJsonPath('trace.awaiting_node_id', null);

        $this->getJson(route('neuronai-studio.workflows.traces.show.json', $running))
            ->assertOk()
            ->assertJsonPath('trace.status', 'running');

        $this->getJson(route('neuronai-studio.workflows.traces.show.json', $awaiting))
            ->assertOk()
            ->assertJsonPath('trace.status', 'awaiting_input')
            ->assertJsonPath('trace.awaiting_node_id', 'human_1');
    }
}
