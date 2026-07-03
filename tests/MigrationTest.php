<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Models\WorkflowTrace;
use DigitalElvis\NeuronAIStudio\Support\StudioTables;

class MigrationTest extends TestCase
{
    public function test_migrations_create_tables(): void
    {
        $this->assertTrue(\Schema::hasTable('agent_definitions'));
        $this->assertTrue(\Schema::hasTable('workflow_definitions'));
        $this->assertTrue(\Schema::hasTable(StudioTables::name('workflow_traces')));
        $this->assertTrue(\Schema::hasTable(StudioTables::name('workflow_trace_steps')));
        $this->assertTrue(\Schema::hasTable(StudioTables::name('workflow_checkpoints')));
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

        $trace = WorkflowTrace::create([
            'workflow_definition_id' => $workflow->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('agent_definitions', ['id' => $agent->id]);
        $this->assertDatabaseHas('workflow_definitions', ['id' => $workflow->id]);
        $this->assertDatabaseHas(StudioTables::name('workflow_traces'), ['id' => $trace->id]);
    }
}
