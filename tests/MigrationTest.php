<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioTrace;
use DigitalElvis\NeuronAIStudio\Models\StudioTraceSpan;
use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Support\Str;

class MigrationTest extends TestCase
{
    public function test_migrations_create_tables(): void
    {
        $this->assertTrue(\Schema::hasTable('agent_definitions'));
        $this->assertTrue(\Schema::hasTable('workflow_definitions'));
        $this->assertTrue(\Schema::hasTable(StudioTables::name('threads')));
        $this->assertTrue(\Schema::hasTable(StudioTables::name('runs')));
        $this->assertTrue(\Schema::hasTable(StudioTables::name('traces')));
        $this->assertTrue(\Schema::hasTable(StudioTables::name('trace_spans')));
        $this->assertTrue(\Schema::hasTable('mcp_servers'));
        $this->assertTrue(\Schema::hasTable('agent_mcp_server'));
    }

    public function test_models_can_persist_records(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Test Agent',
            'slug' => 'test-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
        ]);

        $workflow = WorkflowDefinition::create([
            'name' => 'Test Workflow',
            'slug' => 'test-workflow',
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
        ]);

        $trace = StudioTrace::create([
            'id' => (string) Str::uuid(),
            'run_id' => $run->id,
        ]);

        $span = StudioTraceSpan::create([
            'id' => (string) Str::uuid(),
            'trace_id' => $trace->id,
            'name' => 'start_node',
            'type' => 'node',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('agent_definitions', ['id' => $agent->id]);
        $this->assertDatabaseHas('workflow_definitions', ['id' => $workflow->id]);
        $this->assertDatabaseHas(StudioTables::name('threads'), ['id' => $thread->id]);
        $this->assertDatabaseHas(StudioTables::name('runs'), ['id' => $run->id]);
        $this->assertDatabaseHas(StudioTables::name('traces'), ['id' => $trace->id]);
        $this->assertDatabaseHas(StudioTables::name('trace_spans'), ['id' => $span->id]);
    }
}
