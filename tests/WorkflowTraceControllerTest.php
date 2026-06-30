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
}
